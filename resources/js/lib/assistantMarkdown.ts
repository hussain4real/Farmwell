export type InlineToken =
    | { type: 'text'; content: string }
    | { type: 'strong'; content: string }
    | { type: 'em'; content: string }
    | { type: 'code'; content: string }
    | { type: 'link'; label: string; href: string };

export type TableAlignment = 'left' | 'center' | 'right';

export type TableRow = {
    cells: string[];
    highlighted: boolean;
};

export type MarkdownBlock =
    | { type: 'heading'; level: 1 | 2 | 3; text: string }
    | {
          type: 'table';
          headers: string[];
          alignments: TableAlignment[];
          rows: TableRow[];
      }
    | { type: 'list'; ordered: boolean; items: string[] }
    | { type: 'blockquote'; lines: string[] }
    | { type: 'code'; language: string; code: string }
    | { type: 'divider' }
    | { type: 'paragraph'; lines: string[] };

const TABLE_DIVIDER_RE =
    /^\s*\|?(?:\s*:?-{2,}:?\s*\|)+\s*(?:\s*:?-{2,}:?\s*\|?)?\s*$/;
const HEADING_RE = /^(#{1,6})\s+(.*)$/;
const UNORDERED_ITEM_RE = /^\s*(?:[-*•])\s+(.*)$/;
const ORDERED_ITEM_RE = /^\s*\d+[.)]\s+(.*)$/;
const BLOCKQUOTE_RE = /^\s*>\s?(.*)$/;
const FENCE_RE = /^\s*```([\w-]*)\s*$/;
const DIVIDER_RE = /^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/;

function isSafeUrl(rawHref: string): boolean {
    const trimmed = rawHref.trim();

    if (trimmed === '') {
        return false;
    }

    if (trimmed.startsWith('/') && !trimmed.startsWith('//')) {
        return true;
    }

    try {
        const parsed = new URL(trimmed);

        return parsed.protocol === 'https:' || parsed.protocol === 'http:';
    } catch {
        return false;
    }
}

function splitTableRow(line: string): string[] {
    let working = line.trim();

    if (working.startsWith('|')) {
        working = working.slice(1);
    }

    if (working.endsWith('|')) {
        working = working.slice(0, -1);
    }

    return working.split('|').map((cell) => cell.trim());
}

function parseAlignments(
    dividerLine: string,
    columnCount: number,
): TableAlignment[] {
    const rawCells = splitTableRow(dividerLine);

    return Array.from({ length: columnCount }, (_, index): TableAlignment => {
        const token = (rawCells[index] ?? '').trim();
        const startsColon = token.startsWith(':');
        const endsColon = token.endsWith(':');

        if (startsColon && endsColon) {
            return 'center';
        }

        if (endsColon) {
            return 'right';
        }

        return 'left';
    });
}

function isHighlightedSummaryRow(firstCell: string): boolean {
    const trimmed = firstCell.trim();

    if (/^\*\*.+\*\*$/.test(trimmed) || /^__.+__$/.test(trimmed)) {
        return true;
    }

    const plain = trimmed.replace(/[*_`]/g, '').trim();

    return /^(?:total|net\b|current balance|balance\b|remaining balance)/i.test(
        plain,
    );
}

function isTableStart(lines: string[], index: number): boolean {
    if (index + 1 >= lines.length) {
        return false;
    }

    const headerLine = lines[index];
    const dividerLine = lines[index + 1];

    if (!headerLine.includes('|') || !TABLE_DIVIDER_RE.test(dividerLine)) {
        return false;
    }

    const headers = splitTableRow(headerLine);
    const dividers = splitTableRow(dividerLine);

    return headers.length >= 2 && dividers.length >= 2;
}

export function parseInlineMarkdown(text: string): InlineToken[] {
    const tokens: InlineToken[] = [];
    let cursor = 0;

    const pushText = (chunk: string): void => {
        if (!chunk) {
            return;
        }

        const last = tokens[tokens.length - 1];

        if (last && last.type === 'text') {
            last.content += chunk;
        } else {
            tokens.push({ type: 'text', content: chunk });
        }
    };

    const inlineRe =
        /`([^`\n]+)`|\[([^\]\n]+)\]\(((?:[^()\n]|\([^()\n]*\))+)\)|\*\*([^*\n]+)\*\*|__([^_\n]+)__|\*([^*\n]+)\*|_([^_\n]+)_/g;

    for (const match of text.matchAll(inlineRe)) {
        const index = match.index ?? 0;

        if (index > cursor) {
            pushText(text.slice(cursor, index));
        }

        const [
            full,
            code,
            linkLabel,
            linkHref,
            boldStar,
            boldUnder,
            italicStar,
            italicUnder,
        ] = match;

        if (code !== undefined) {
            tokens.push({ type: 'code', content: code });
        } else if (linkLabel !== undefined && linkHref !== undefined) {
            if (isSafeUrl(linkHref)) {
                tokens.push({
                    type: 'link',
                    label: linkLabel,
                    href: linkHref.trim(),
                });
            } else {
                pushText(linkLabel);
            }
        } else if (boldStar !== undefined || boldUnder !== undefined) {
            tokens.push({
                type: 'strong',
                content: boldStar ?? boldUnder ?? '',
            });
        } else if (italicStar !== undefined || italicUnder !== undefined) {
            tokens.push({
                type: 'em',
                content: italicStar ?? italicUnder ?? '',
            });
        } else {
            pushText(full);
        }

        cursor = index + full.length;
    }

    if (cursor < text.length) {
        pushText(text.slice(cursor));
    }

    return tokens;
}

export function parseAssistantMarkdown(source: string): MarkdownBlock[] {
    const normalized = source.replace(/\r\n?/g, '\n').trim();

    if (normalized === '') {
        return [];
    }

    const lines = normalized.split('\n');
    const blocks: MarkdownBlock[] = [];
    let i = 0;

    while (i < lines.length) {
        const line = lines[i];

        if (line.trim() === '') {
            i++;
            continue;
        }

        const fenceMatch = line.match(FENCE_RE);

        if (fenceMatch) {
            const language = fenceMatch[1] ?? '';
            const codeLines: string[] = [];
            i++;

            while (i < lines.length && !FENCE_RE.test(lines[i])) {
                codeLines.push(lines[i]);
                i++;
            }

            if (i < lines.length && FENCE_RE.test(lines[i])) {
                i++;
            }

            blocks.push({
                type: 'code',
                language,
                code: codeLines.join('\n'),
            });
            continue;
        }

        if (isTableStart(lines, i)) {
            const headers = splitTableRow(lines[i]);
            const alignments = parseAlignments(lines[i + 1], headers.length);
            const rows: TableRow[] = [];
            i += 2;

            while (i < lines.length) {
                const rowLine = lines[i];

                if (rowLine.trim() === '' || !rowLine.includes('|')) {
                    break;
                }

                const rawCells = splitTableRow(rowLine);
                const normalizedCells = headers.map(
                    (_, colIndex) => rawCells[colIndex] ?? '',
                );

                rows.push({
                    cells: normalizedCells,
                    highlighted: isHighlightedSummaryRow(
                        normalizedCells[0] ?? '',
                    ),
                });
                i++;
            }

            blocks.push({
                type: 'table',
                headers,
                alignments,
                rows,
            });
            continue;
        }

        const headingMatch = line.match(HEADING_RE);

        if (headingMatch) {
            const rawLevel = headingMatch[1].length;
            const level: 1 | 2 | 3 = rawLevel <= 1 ? 1 : rawLevel === 2 ? 2 : 3;

            blocks.push({
                type: 'heading',
                level,
                text: headingMatch[2].trim(),
            });
            i++;
            continue;
        }

        if (DIVIDER_RE.test(line)) {
            blocks.push({ type: 'divider' });
            i++;
            continue;
        }

        if (BLOCKQUOTE_RE.test(line)) {
            const quoteLines: string[] = [];

            while (i < lines.length) {
                const match = lines[i].match(BLOCKQUOTE_RE);

                if (!match) {
                    break;
                }

                quoteLines.push(match[1]);
                i++;
            }

            blocks.push({
                type: 'blockquote',
                lines: quoteLines,
            });
            continue;
        }

        if (UNORDERED_ITEM_RE.test(line) || ORDERED_ITEM_RE.test(line)) {
            const ordered = ORDERED_ITEM_RE.test(line);
            const pattern = ordered ? ORDERED_ITEM_RE : UNORDERED_ITEM_RE;
            const items: string[] = [];

            while (i < lines.length) {
                const match = lines[i].match(pattern);

                if (!match) {
                    break;
                }

                items.push(match[1].trim());
                i++;
            }

            blocks.push({
                type: 'list',
                ordered,
                items,
            });
            continue;
        }

        const paragraphLines: string[] = [];

        while (i < lines.length) {
            const current = lines[i];

            if (
                current.trim() === '' ||
                FENCE_RE.test(current) ||
                HEADING_RE.test(current) ||
                DIVIDER_RE.test(current) ||
                BLOCKQUOTE_RE.test(current) ||
                UNORDERED_ITEM_RE.test(current) ||
                ORDERED_ITEM_RE.test(current) ||
                isTableStart(lines, i)
            ) {
                break;
            }

            paragraphLines.push(current);
            i++;
        }

        if (paragraphLines.length > 0) {
            blocks.push({
                type: 'paragraph',
                lines: paragraphLines,
            });
        }
    }

    return blocks;
}
