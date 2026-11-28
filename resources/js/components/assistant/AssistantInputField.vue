<script setup lang="ts">
import { AlertCircle, Check, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import type { AssistantInputField } from '@/components/assistant/AssistantInputCard.vue';
import { Input } from '@/components/ui/input';

type Props = {
    formId: string;
    field: AssistantInputField;
    currencyPrefix: string;
    modelValue: string | string[];
    error?: string;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    error: undefined,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | string[]];
    blur: [];
    change: [];
}>();

const inputId = computed(
    () => `assistant-form-${props.formId}-${props.field.name}`,
);
const hintId = computed(() => `${inputId.value}-hint`);
const errorId = computed(() => `${inputId.value}-error`);

const describedBy = computed(() => {
    const ids: string[] = [];

    if (props.field.hint) {
        ids.push(hintId.value);
    }

    if (props.error) {
        ids.push(errorId.value);
    }

    return ids.length > 0 ? ids.join(' ') : undefined;
});

function isMultiSelected(optionValue: string): boolean {
    return (
        Array.isArray(props.modelValue) &&
        props.modelValue.includes(optionValue)
    );
}

function toggleMultiOption(optionValue: string): void {
    const current = Array.isArray(props.modelValue)
        ? [...props.modelValue]
        : [];
    const index = current.indexOf(optionValue);

    if (index >= 0) {
        current.splice(index, 1);
    } else {
        current.push(optionValue);
    }

    emit('update:modelValue', current);
    emit('change');
}

function updateTextValue(next: string | number): void {
    emit('update:modelValue', String(next));
}
</script>

<template>
    <div class="space-y-1.5">
        <template v-if="field.type === 'radio'">
            <fieldset class="space-y-2">
                <legend class="text-xs font-medium text-foreground">
                    {{ field.label }}
                    <span
                        v-if="field.required !== false"
                        class="text-destructive"
                        aria-hidden="true"
                        >*</span
                    >
                </legend>
                <p
                    v-if="field.hint"
                    :id="hintId"
                    class="text-[11px] text-muted-foreground"
                >
                    {{ field.hint }}
                </p>
                <div class="grid grid-cols-1 gap-2">
                    <label
                        v-for="(opt, optIdx) in field.options ?? []"
                        :key="opt.value"
                        :for="`${inputId}-${optIdx}`"
                        class="flex min-h-11 cursor-pointer items-start gap-2.5 rounded-xl border px-3 py-2.5 transition-colors focus-within:ring-2 focus-within:ring-primary/30"
                        :class="
                            modelValue === opt.value
                                ? 'border-primary/60 bg-primary/[0.08] text-foreground'
                                : 'border-border/80 bg-background/70 text-foreground/90 hover:border-primary/35'
                        "
                    >
                        <input
                            :id="`${inputId}-${optIdx}`"
                            type="radio"
                            :name="field.name"
                            :value="opt.value"
                            :checked="modelValue === opt.value"
                            :required="field.required !== false"
                            :aria-invalid="error ? 'true' : 'false'"
                            :aria-describedby="describedBy"
                            :disabled="disabled"
                            class="sr-only"
                            @change="
                                emit('update:modelValue', opt.value);
                                emit('change');
                            "
                        />
                        <span
                            class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                            :class="
                                modelValue === opt.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-muted-foreground/50'
                            "
                            aria-hidden="true"
                        >
                            <span
                                v-if="modelValue === opt.value"
                                class="size-1.5 rounded-full bg-current"
                            />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-medium">{{
                                opt.label
                            }}</span>
                            <span
                                v-if="opt.hint"
                                class="mt-0.5 block text-[11px] text-muted-foreground"
                                >{{ opt.hint }}</span
                            >
                        </span>
                    </label>
                </div>
            </fieldset>
        </template>

        <template v-else-if="field.type === 'multiselect'">
            <fieldset class="space-y-2">
                <legend class="text-xs font-medium text-foreground">
                    {{ field.label }}
                    <span
                        v-if="field.required !== false"
                        class="text-destructive"
                        aria-hidden="true"
                        >*</span
                    >
                </legend>
                <p
                    v-if="field.hint"
                    :id="hintId"
                    class="text-[11px] text-muted-foreground"
                >
                    {{ field.hint }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="(opt, optIdx) in field.options ?? []"
                        :key="opt.value"
                        :for="`${inputId}-${optIdx}`"
                        class="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors focus-within:ring-2 focus-within:ring-primary/30"
                        :class="
                            isMultiSelected(opt.value)
                                ? 'border-primary/60 bg-primary/15 text-primary'
                                : 'border-border/80 bg-background/70 text-foreground/85 hover:border-primary/35'
                        "
                    >
                        <input
                            :id="`${inputId}-${optIdx}`"
                            type="checkbox"
                            :name="field.name"
                            :value="opt.value"
                            :checked="isMultiSelected(opt.value)"
                            :aria-invalid="error ? 'true' : 'false'"
                            :aria-describedby="describedBy"
                            :disabled="disabled"
                            class="sr-only"
                            @change="toggleMultiOption(opt.value)"
                        />
                        <Check
                            v-if="isMultiSelected(opt.value)"
                            class="size-3 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{{ opt.label }}</span>
                    </label>
                </div>
            </fieldset>
        </template>

        <template v-else>
            <label
                :for="inputId"
                class="block text-xs font-medium text-foreground"
            >
                {{ field.label }}
                <span
                    v-if="field.required !== false"
                    class="text-destructive"
                    aria-hidden="true"
                    >*</span
                >
            </label>
            <p
                v-if="field.hint"
                :id="hintId"
                class="text-[11px] text-muted-foreground"
            >
                {{ field.hint }}
            </p>

            <div
                v-if="field.type === 'currency'"
                class="flex min-h-10 items-center overflow-hidden rounded-lg border bg-background transition-colors focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                :class="error ? 'border-destructive' : 'border-input'"
            >
                <span
                    class="flex h-full shrink-0 items-center border-r border-input bg-muted/50 px-3 text-xs font-semibold text-muted-foreground"
                >
                    {{ currencyPrefix }}
                </span>
                <input
                    :id="inputId"
                    :value="typeof modelValue === 'string' ? modelValue : ''"
                    type="text"
                    inputmode="decimal"
                    enterkeyhint="next"
                    :name="field.name"
                    :required="field.required !== false"
                    :placeholder="field.placeholder ?? '0.00'"
                    :aria-invalid="error ? 'true' : 'false'"
                    :aria-describedby="describedBy"
                    :disabled="disabled"
                    class="min-h-10 w-full bg-transparent px-3 py-2 text-base tabular-nums outline-none placeholder:text-muted-foreground sm:text-sm"
                    @blur="emit('blur')"
                    @input="
                        updateTextValue(
                            ($event.target as HTMLInputElement).value,
                        );
                        emit('change');
                    "
                />
            </div>

            <div v-else-if="field.type === 'select'" class="relative">
                <select
                    :id="inputId"
                    :value="typeof modelValue === 'string' ? modelValue : ''"
                    :name="field.name"
                    :required="field.required !== false"
                    :aria-invalid="error ? 'true' : 'false'"
                    :aria-describedby="describedBy"
                    :disabled="disabled"
                    class="min-h-10 w-full appearance-none rounded-lg border bg-background py-2 pr-9 pl-3 text-base text-foreground transition-colors outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20 sm:text-sm"
                    :class="error ? 'border-destructive' : 'border-input'"
                    @blur="emit('blur')"
                    @change="
                        updateTextValue(
                            ($event.target as HTMLSelectElement).value,
                        );
                        emit('change');
                    "
                >
                    <option value="">
                        {{ field.placeholder ?? 'Select an option…' }}
                    </option>
                    <option
                        v-for="opt in field.options ?? []"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>
                <ChevronDown
                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            <textarea
                v-else-if="field.type === 'textarea'"
                :id="inputId"
                :value="typeof modelValue === 'string' ? modelValue : ''"
                rows="2"
                :name="field.name"
                :required="field.required !== false"
                :placeholder="field.placeholder"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="describedBy"
                :disabled="disabled"
                class="min-h-20 w-full rounded-lg border bg-background px-3 py-2 text-base text-foreground transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20 sm:text-sm"
                :class="error ? 'border-destructive' : 'border-input'"
                @blur="emit('blur')"
                @input="
                    updateTextValue(
                        ($event.target as HTMLTextAreaElement).value,
                    );
                    emit('change');
                "
            />

            <input
                v-else-if="field.type === 'date'"
                :id="inputId"
                :value="typeof modelValue === 'string' ? modelValue : ''"
                type="date"
                :name="field.name"
                :required="field.required !== false"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="describedBy"
                :disabled="disabled"
                class="min-h-10 w-full rounded-lg border bg-background px-3 py-2 text-base text-foreground transition-colors outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20 sm:text-sm"
                :class="error ? 'border-destructive' : 'border-input'"
                @blur="emit('blur')"
                @change="
                    updateTextValue(($event.target as HTMLInputElement).value);
                    emit('change');
                "
            />

            <Input
                v-else
                :id="inputId"
                :model-value="typeof modelValue === 'string' ? modelValue : ''"
                type="text"
                :inputmode="field.type === 'number' ? 'decimal' : 'text'"
                :name="field.name"
                :required="field.required !== false"
                :placeholder="field.placeholder"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="describedBy"
                :disabled="disabled"
                class="min-h-10 text-base sm:text-sm"
                :class="[
                    field.type === 'number' ? 'tabular-nums' : '',
                    error ? 'border-destructive' : '',
                ]"
                @blur="emit('blur')"
                @update:model-value="
                    updateTextValue($event);
                    emit('change');
                "
            />
        </template>

        <p
            v-if="error"
            :id="errorId"
            class="flex items-center gap-1.5 text-xs text-destructive"
        >
            <AlertCircle class="size-3.5 shrink-0" aria-hidden="true" />
            <span>{{ error }}</span>
        </p>
    </div>
</template>
