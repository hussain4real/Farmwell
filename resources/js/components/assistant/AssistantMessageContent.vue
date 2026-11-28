<script setup lang="ts">
import { computed } from 'vue';
import AssistantInlineTokens from '@/components/assistant/AssistantInlineTokens.vue';
import { parseAssistantMarkdown } from '@/lib/assistantMarkdown';
import type { MarkdownBlock, TableAlignment } from '@/lib/assistantMarkdown';

type Props = {
    content: string;
};

const props = defineProps<Props>();

const blocks = computed<MarkdownBlock[]>(() =>
    parseAssistantMarkdown(props.content),
);

function isNumericLookingCell(raw: string): boolean {
    const stripped = raw.replace(/[*_`]/g, '').trim();

    return /^(?:[₦$€£]|[A-Z]{3}\s)?[-+]?\d[\d,]*(?:\.\d+)?%?$/.test(stripped);
}

function cellAlignmentClass(
    alignment: TableAlignment | undefined,
    rawCell: string,
): string {
    if (alignment === 'right' || isNumericLookingCell(rawCell)) {
        return 'text-right tabular-nums whitespace-nowrap';
    }

    if (alignment === 'center') {
        return 'text-center';
    }

    return 'text-left';
}

function headerAlignmentClass(alignment: TableAlignment | undefined): string {
    if (alignment === 'right') {
        return 'text-right';
    }

    if (alignment === 'center') {
        return 'text-center';
    }

    return 'text-left';
}

function isHeadingSubtitlePair(index: number): boolean {
    const previous = index > 0 ? blocks.value[index - 1] : null;
    const current = blocks.value[index];

    if (
        !previous ||
        previous.type !== 'heading' ||
        current.type !== 'paragraph'
    ) {
        return false;
    }

    return (
        current.lines.length === 1 &&
        /^\*\*.+\*\*$/.test(current.lines[0].trim())
    );
}
</script>

<template>
    <div class="space-y-2.5 text-[13.5px] leading-relaxed text-foreground">
        <template v-for="(block, blockIndex) in blocks" :key="blockIndex">
            <h1
                v-if="block.type === 'heading' && block.level === 1"
                class="text-[15px] font-semibold tracking-tight text-foreground"
            >
                <AssistantInlineTokens :text="block.text" />
            </h1>

            <h2
                v-else-if="block.type === 'heading' && block.level === 2"
                class="text-sm font-semibold tracking-tight text-foreground"
            >
                <AssistantInlineTokens :text="block.text" />
            </h2>

            <h3
                v-else-if="block.type === 'heading'"
                class="text-[13px] font-semibold tracking-tight text-foreground"
            >
                <AssistantInlineTokens :text="block.text" />
            </h3>

            <div
                v-else-if="block.type === 'table'"
                tabindex="0"
                role="region"
                aria-label="Summary table"
                class="my-2 overflow-x-auto rounded-xl border border-border/80 bg-background/70 shadow-xs focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none dark:bg-background/40"
            >
                <table class="w-full border-separate border-spacing-0 text-xs">
                    <thead
                        class="sticky top-0 z-10 bg-muted/75 backdrop-blur-xs"
                    >
                        <tr>
                            <th
                                v-for="(header, colIndex) in block.headers"
                                :key="colIndex"
                                scope="col"
                                class="border-b border-border/80 px-3 py-2 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                :class="
                                    headerAlignmentClass(
                                        block.alignments[colIndex],
                                    )
                                "
                            >
                                <AssistantInlineTokens
                                    :text="header"
                                    :allow-links="false"
                                    code-class="rounded bg-muted px-1 py-0.5 font-mono text-[11px]"
                                />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, rowIndex) in block.rows"
                            :key="rowIndex"
                            :class="
                                row.highlighted
                                    ? 'bg-primary/[0.08] font-semibold text-foreground dark:bg-primary/[0.15]'
                                    : rowIndex % 2 === 1
                                      ? 'bg-muted/20'
                                      : ''
                            "
                        >
                            <template
                                v-for="(cell, colIndex) in row.cells"
                                :key="colIndex"
                            >
                                <th
                                    v-if="colIndex === 0"
                                    scope="row"
                                    class="border-b border-border/50 px-3 py-2 text-left font-medium text-foreground/90"
                                    :class="[
                                        row.highlighted
                                            ? 'font-semibold text-foreground'
                                            : '',
                                        rowIndex === block.rows.length - 1
                                            ? 'border-b-0'
                                            : '',
                                    ]"
                                >
                                    <AssistantInlineTokens
                                        :text="cell"
                                        :allow-links="false"
                                        code-class="rounded bg-muted px-1 py-0.5 font-mono text-[11px]"
                                    />
                                </th>
                                <td
                                    v-else
                                    class="border-b border-border/50 px-3 py-2 text-foreground/90"
                                    :class="[
                                        cellAlignmentClass(
                                            block.alignments[colIndex],
                                            cell,
                                        ),
                                        row.highlighted
                                            ? 'font-semibold text-primary'
                                            : '',
                                        rowIndex === block.rows.length - 1
                                            ? 'border-b-0'
                                            : '',
                                    ]"
                                >
                                    <AssistantInlineTokens
                                        :text="cell"
                                        code-class="rounded bg-muted px-1 py-0.5 font-mono text-[11px]"
                                    />
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ol
                v-else-if="block.type === 'list' && block.ordered"
                class="list-decimal space-y-1.5 pl-5 marker:font-medium marker:text-primary"
            >
                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                    <AssistantInlineTokens :text="item" />
                </li>
            </ol>

            <ul
                v-else-if="block.type === 'list'"
                class="list-disc space-y-1.5 pl-5 marker:text-primary"
            >
                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                    <AssistantInlineTokens :text="item" />
                </li>
            </ul>

            <blockquote
                v-else-if="block.type === 'blockquote'"
                class="rounded-r-lg border-l-2 border-primary/50 bg-primary/[0.05] px-3 py-2 text-xs leading-relaxed text-foreground/90"
            >
                <p v-for="(line, lineIndex) in block.lines" :key="lineIndex">
                    <AssistantInlineTokens
                        :text="line"
                        code-class="rounded bg-muted px-1 py-0.5 font-mono text-[11px]"
                    />
                </p>
            </blockquote>

            <pre
                v-else-if="block.type === 'code'"
                class="overflow-x-auto rounded-lg border border-border/70 bg-muted/60 p-3 font-mono text-xs text-foreground"
            ><code>{{ block.code }}</code></pre>

            <hr v-else-if="block.type === 'divider'" class="border-border/70" />

            <p
                v-else-if="block.type === 'paragraph'"
                class="text-foreground/90"
                :class="
                    isHeadingSubtitlePair(blockIndex)
                        ? '-mt-1.5 text-xs text-muted-foreground'
                        : ''
                "
            >
                <template
                    v-for="(line, lineIndex) in block.lines"
                    :key="lineIndex"
                >
                    <br v-if="lineIndex > 0" />
                    <AssistantInlineTokens
                        :text="line"
                        strong-class="font-semibold text-foreground"
                    />
                </template>
            </p>
        </template>
    </div>
</template>
