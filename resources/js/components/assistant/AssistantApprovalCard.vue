<script setup lang="ts">
import { Check, Pencil, ShieldAlert, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type PendingApprovalItem = {
    id: string;
    tool: string;
    arguments: Record<string, unknown>;
    reason?: string | null;
};

type Props = {
    approval: PendingApprovalItem;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});

const emit = defineEmits<{
    resolve: [
        payload: {
            id: string;
            action: 'approve' | 'edit' | 'reject';
            arguments?: Record<string, unknown>;
            reason?: string;
        },
    ];
}>();

const TOOL_LABELS: Record<string, string> = {
    RecordExpenseTool: 'Record Expense',
    CreateFundingPhaseTool: 'Create Funding Phase',
    RecordFundingReleaseTool: 'Record Funding Release',
    UpdateAgreementFundingTool: 'Update Agreement Funding',
    RecordDailyLogTool: 'Record Daily Log',
    RecordFarmActivityTool: 'Record Farm Activity',
    DecideApprovalRequestTool: 'Decide Approval Request',
};

const editing = ref(false);
const draftValues = ref<Record<string, string>>({});

function resetDrafts(args: Record<string, unknown>): void {
    const next: Record<string, string> = {};

    for (const [key, value] of Object.entries(args)) {
        if (value === null || value === undefined) {
            next[key] = '';
        } else if (typeof value === 'object') {
            next[key] = JSON.stringify(value);
        } else {
            next[key] = String(value);
        }
    }

    draftValues.value = next;
}

watch(
    () => props.approval,
    (next) => {
        editing.value = false;
        resetDrafts(next.arguments ?? {});
    },
    { immediate: true, deep: true },
);

const toolTitle = computed(() => {
    if (TOOL_LABELS[props.approval.tool]) {
        return TOOL_LABELS[props.approval.tool];
    }

    return props.approval.tool
        .replace(/Tool$/, '')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .trim();
});

const argumentEntries = computed(() =>
    Object.entries(props.approval.arguments ?? {}).map(([key, value]) => ({
        key,
        label: key.replace(/_/g, ' '),
        display:
            value === null || value === undefined || value === ''
                ? '—'
                : typeof value === 'object'
                  ? JSON.stringify(value)
                  : String(value),
    })),
);

function coerceEditedArguments(): Record<string, unknown> {
    const original = props.approval.arguments ?? {};
    const parsed: Record<string, unknown> = {};

    for (const [key, raw] of Object.entries(draftValues.value)) {
        const previous = original[key];
        const trimmed = raw.trim();

        if (typeof previous === 'number') {
            const numeric = Number(trimmed);
            parsed[key] =
                trimmed !== '' && Number.isFinite(numeric) ? numeric : previous;
            continue;
        }

        if (typeof previous === 'boolean') {
            parsed[key] = trimmed.toLowerCase() === 'true' || trimmed === '1';
            continue;
        }

        if (previous === null && trimmed === '') {
            parsed[key] = null;
            continue;
        }

        if (typeof previous === 'object' && previous !== null) {
            try {
                parsed[key] = JSON.parse(raw);
            } catch {
                parsed[key] = previous;
            }

            continue;
        }

        parsed[key] = raw;
    }

    return parsed;
}

function submitApprove(): void {
    emit('resolve', { id: props.approval.id, action: 'approve' });
}

function submitEdit(): void {
    emit('resolve', {
        id: props.approval.id,
        action: 'edit',
        arguments: coerceEditedArguments(),
    });
    editing.value = false;
}

function submitReject(): void {
    emit('resolve', {
        id: props.approval.id,
        action: 'reject',
        reason: 'Declined by user.',
    });
}
</script>

<template>
    <div
        class="rounded-xl border border-amber-300/80 bg-amber-50/70 p-3.5 text-sm shadow-xs dark:border-amber-800/80 dark:bg-amber-950/30"
        data-testid="assistant-approval-card"
    >
        <div class="flex items-start justify-between gap-2">
            <div
                class="flex items-center gap-2 font-medium text-amber-950 dark:text-amber-100"
            >
                <ShieldAlert
                    class="size-4 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <span>{{ toolTitle }}</span>
            </div>
            <Badge
                variant="outline"
                class="border-amber-400/70 bg-amber-100/70 text-[11px] text-amber-900 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200"
            >
                Approval required
            </Badge>
        </div>

        <p
            v-if="approval.reason"
            class="mt-2 text-xs leading-relaxed text-amber-900/90 dark:text-amber-100/90"
        >
            {{ approval.reason }}
        </p>

        <dl
            v-if="!editing && argumentEntries.length > 0"
            class="mt-2.5 grid grid-cols-1 gap-1.5 rounded-lg border border-amber-200/70 bg-background/80 p-2.5 text-xs dark:border-amber-900/70"
        >
            <div
                v-for="entry in argumentEntries"
                :key="entry.key"
                class="flex items-start justify-between gap-3"
            >
                <dt class="shrink-0 text-muted-foreground capitalize">
                    {{ entry.label }}
                </dt>
                <dd
                    class="max-w-[65%] text-right font-medium break-words text-foreground tabular-nums"
                >
                    {{ entry.display }}
                </dd>
            </div>
        </dl>

        <div
            v-else-if="editing"
            class="mt-2.5 space-y-2 rounded-lg border border-amber-200/70 bg-background p-2.5 dark:border-amber-900/70"
        >
            <div
                v-for="entry in argumentEntries"
                :key="entry.key"
                class="space-y-1"
            >
                <Label
                    :for="`approval-${approval.id}-${entry.key}`"
                    class="text-xs text-muted-foreground capitalize"
                >
                    {{ entry.label }}
                </Label>
                <Input
                    :id="`approval-${approval.id}-${entry.key}`"
                    v-model="draftValues[entry.key]"
                    class="h-8 text-xs"
                    :disabled="disabled"
                />
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <Button
                v-if="!editing"
                type="button"
                size="sm"
                class="h-8 gap-1.5"
                :disabled="disabled"
                @click="submitApprove"
            >
                <Check class="size-3.5" />
                <span>Approve</span>
            </Button>
            <Button
                v-else
                type="button"
                size="sm"
                class="h-8 gap-1.5"
                :disabled="disabled"
                @click="submitEdit"
            >
                <Check class="size-3.5" />
                <span>Save &amp; Approve</span>
            </Button>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-8 gap-1.5 border-destructive/40 bg-destructive/5 text-destructive hover:bg-destructive/15 hover:text-destructive"
                :disabled="disabled"
                @click="submitReject"
            >
                <X class="size-3.5" />
                <span>Decline</span>
            </Button>

            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="ml-auto h-8 gap-1.5 text-muted-foreground"
                :disabled="disabled"
                @click="editing = !editing"
            >
                <Pencil class="size-3.5" />
                <span>{{ editing ? 'Back' : 'Edit' }}</span>
            </Button>
        </div>
    </div>
</template>
