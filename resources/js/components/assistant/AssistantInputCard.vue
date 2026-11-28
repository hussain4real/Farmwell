<script setup lang="ts">
import {
    ArrowRight,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import AssistantInputFieldControl from '@/components/assistant/AssistantInputField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type AssistantInputOption = {
    value: string;
    label: string;
    hint?: string;
};

export type AssistantInputFieldType =
    | 'text'
    | 'currency'
    | 'number'
    | 'date'
    | 'select'
    | 'radio'
    | 'multiselect'
    | 'textarea';

export type AssistantInputField = {
    name: string;
    label: string;
    type: AssistantInputFieldType;
    required?: boolean;
    placeholder?: string;
    hint?: string;
    default_value?: string;
    options?: AssistantInputOption[];
};

export type AssistantInputForm = {
    title: string;
    description?: string | null;
    submit_label?: string | null;
    currency?: string | null;
    fields: AssistantInputField[];
};

type Props = {
    formId: string;
    form: AssistantInputForm;
    submitted?: boolean;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    submitted: false,
    disabled: false,
});

const emit = defineEmits<{
    submit: [message: string];
}>();

const CURRENCY_SYMBOLS: Record<string, string> = {
    NGN: '₦',
    USD: '$',
    GBP: '£',
    EUR: '€',
};

const currencyPrefix = computed(() => {
    const code = (props.form.currency ?? 'NGN').toUpperCase();

    return CURRENCY_SYMBOLS[code] ?? code;
});

const requiredFields = computed(() =>
    props.form.fields.filter((field) => field.required !== false),
);

const optionalFields = computed(() =>
    props.form.fields.filter((field) => field.required === false),
);

const visiblePrimaryFields = computed(() =>
    requiredFields.value.length > 0 ? requiredFields.value : props.form.fields,
);

const collapsibleOptionalFields = computed(() =>
    requiredFields.value.length > 0 ? optionalFields.value : [],
);

const showOptional = ref(false);
const values = reactive<Record<string, string | string[]>>({});
const touched = reactive<Record<string, boolean>>({});
const errors = reactive<Record<string, string | undefined>>({});
const formElement = ref<HTMLFormElement | null>(null);

watch(
    () => props.form,
    (next) => {
        for (const key of Object.keys(values)) {
            delete values[key];
            delete touched[key];
            delete errors[key];
        }

        for (const field of next.fields) {
            if (field.type === 'multiselect') {
                values[field.name] = field.default_value
                    ? field.default_value
                          .split(',')
                          .map((part) => part.trim())
                          .filter(Boolean)
                    : [];
            } else {
                values[field.name] = field.default_value ?? '';
            }
        }

        showOptional.value = false;
    },
    { immediate: true, deep: true },
);

function validateField(field: AssistantInputField): string | undefined {
    const rawValue = values[field.name];
    const isRequired = field.required !== false;

    if (field.type === 'multiselect') {
        const selected = Array.isArray(rawValue) ? rawValue : [];

        if (isRequired && selected.length === 0) {
            return `Select at least one option for ${field.label.toLowerCase()}.`;
        }

        return undefined;
    }

    const strValue = typeof rawValue === 'string' ? rawValue.trim() : '';

    if (isRequired && strValue === '') {
        if (field.type === 'select' || field.type === 'radio') {
            return `Choose an option for ${field.label.toLowerCase()}.`;
        }

        return `Enter ${field.label.toLowerCase()}.`;
    }

    if (
        strValue !== '' &&
        (field.type === 'currency' || field.type === 'number')
    ) {
        const cleaned = strValue.replace(/,/g, '');
        const numeric = Number(cleaned);

        if (!Number.isFinite(numeric) || numeric <= 0) {
            return field.type === 'currency'
                ? 'Enter a valid amount greater than zero.'
                : 'Enter a valid number greater than zero.';
        }
    }

    return undefined;
}

function handleBlur(field: AssistantInputField): void {
    touched[field.name] = true;
    errors[field.name] = validateField(field);
}

function handleChange(field: AssistantInputField): void {
    if (touched[field.name] || errors[field.name]) {
        errors[field.name] = validateField(field);
    }
}

function formatOptionSummary(
    field: AssistantInputField,
    selectedValue: string,
): string {
    const matched = field.options?.find((opt) => opt.value === selectedValue);

    if (!matched) {
        return selectedValue;
    }

    return matched.label === matched.value
        ? matched.label
        : `${matched.label} (${matched.value})`;
}

function handleSubmit(): void {
    if (props.disabled || props.submitted) {
        return;
    }

    let firstInvalidField: AssistantInputField | null = null;

    for (const field of props.form.fields) {
        touched[field.name] = true;
        const message = validateField(field);
        errors[field.name] = message;

        if (message && !firstInvalidField) {
            firstInvalidField = field;
        }
    }

    if (firstInvalidField) {
        if (
            collapsibleOptionalFields.value.some(
                (item) => item.name === firstInvalidField?.name,
            )
        ) {
            showOptional.value = true;
        }

        const target = formElement.value?.querySelector<HTMLElement>(
            `[name="${CSS.escape(firstInvalidField.name)}"]`,
        );
        target?.focus();

        return;
    }

    const summaryLines: string[] = [];

    for (const field of props.form.fields) {
        const raw = values[field.name];

        if (field.type === 'multiselect') {
            const selected = Array.isArray(raw) ? raw : [];

            if (selected.length > 0) {
                summaryLines.push(
                    `- ${field.label}: ${selected.map((val) => formatOptionSummary(field, val)).join(', ')}`,
                );
            }

            continue;
        }

        const trimmed = typeof raw === 'string' ? raw.trim() : '';

        if (trimmed === '') {
            continue;
        }

        if (field.type === 'currency') {
            const normalized = trimmed.replace(/,/g, '');
            summaryLines.push(
                `- ${field.label}: ${normalized} ${props.form.currency ?? ''}`.trim(),
            );
        } else if (field.type === 'select' || field.type === 'radio') {
            summaryLines.push(
                `- ${field.label}: ${formatOptionSummary(field, trimmed)}`,
            );
        } else {
            summaryLines.push(`- ${field.label}: ${trimmed}`);
        }
    }

    emit('submit', summaryLines.join('\n'));
}
</script>

<template>
    <div
        v-if="submitted"
        class="flex w-full max-w-[96%] items-center justify-between gap-2 rounded-xl border border-emerald-500/25 bg-emerald-500/[0.06] px-3.5 py-2.5 text-xs text-emerald-800 dark:text-emerald-200"
        data-testid="assistant-input-submitted"
    >
        <div class="flex min-w-0 items-center gap-2">
            <CheckCircle2
                class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
            />
            <span class="truncate font-medium">{{ form.title }}</span>
        </div>
        <Badge
            variant="outline"
            class="shrink-0 border-emerald-500/30 bg-emerald-500/10 text-[11px] text-emerald-700 dark:text-emerald-300"
        >
            Submitted
        </Badge>
    </div>

    <form
        v-else
        ref="formElement"
        novalidate
        :aria-labelledby="`assistant-form-title-${formId}`"
        class="w-full max-w-[96%] rounded-2xl border border-primary/25 bg-linear-to-b from-primary/[0.07] via-card to-card p-4 shadow-sm"
        data-testid="assistant-input-card"
        @submit.prevent="handleSubmit"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="flex min-w-0 items-start gap-2.5">
                <div
                    class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/12 text-primary ring-1 ring-primary/20"
                >
                    <SlidersHorizontal class="size-3.5" />
                </div>
                <div class="min-w-0">
                    <h4
                        :id="`assistant-form-title-${formId}`"
                        class="text-sm font-semibold tracking-tight text-foreground"
                    >
                        {{ form.title }}
                    </h4>
                    <p
                        v-if="form.description"
                        class="mt-0.5 text-xs leading-relaxed text-muted-foreground"
                    >
                        {{ form.description }}
                    </p>
                </div>
            </div>
            <Badge
                variant="outline"
                class="shrink-0 border-primary/30 bg-primary/10 text-[11px] text-primary"
            >
                Input needed
            </Badge>
        </div>

        <div class="mt-4 space-y-4">
            <AssistantInputFieldControl
                v-for="field in visiblePrimaryFields"
                :key="field.name"
                v-model="values[field.name]"
                :form-id="formId"
                :field="field"
                :currency-prefix="currencyPrefix"
                :error="errors[field.name]"
                :disabled="disabled"
                @blur="handleBlur(field)"
                @change="handleChange(field)"
            />

            <div
                v-if="collapsibleOptionalFields.length > 0"
                class="border-t border-border/60 pt-3"
            >
                <button
                    type="button"
                    :aria-expanded="showOptional ? 'true' : 'false'"
                    :aria-controls="`assistant-form-optional-${formId}`"
                    class="flex w-full items-center justify-between text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                    @click="showOptional = !showOptional"
                >
                    <span
                        >Optional details ({{
                            collapsibleOptionalFields.length
                        }})</span
                    >
                    <ChevronUp v-if="showOptional" class="size-3.5" />
                    <ChevronDown v-else class="size-3.5" />
                </button>

                <div
                    v-show="showOptional"
                    :id="`assistant-form-optional-${formId}`"
                    class="mt-3 space-y-3.5"
                >
                    <AssistantInputFieldControl
                        v-for="field in collapsibleOptionalFields"
                        :key="field.name"
                        v-model="values[field.name]"
                        :form-id="formId"
                        :field="field"
                        :currency-prefix="currencyPrefix"
                        :error="errors[field.name]"
                        :disabled="disabled"
                        @blur="handleBlur(field)"
                        @change="handleChange(field)"
                    />
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
            <Button
                type="submit"
                size="sm"
                class="h-9 gap-1.5 rounded-xl px-4"
                :disabled="disabled"
            >
                <span>{{ form.submit_label || 'Continue' }}</span>
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </Button>
        </div>
    </form>
</template>
