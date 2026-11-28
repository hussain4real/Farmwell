<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeGradeTransfer } from '@/routes/farms/production-cycles/grade-transfer';
import SlideOverForm from './SlideOverForm.vue';

defineProps<{
    open: boolean;
    teamSlug: string;
    farmId: number;
    cycleId: number;
    units: { id: number; name: string }[];
    siblingCycles: {
        id: number;
        name: string;
        currentStockCount: number | null;
    }[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <SlideOverForm
        :open="open"
        title="Grade & transfer fish"
        description="Move sorted fish (shooters or runts) to another cycle and pond on this farm"
        @update:open="emit('update:open', $event)"
    >
        <Form
            v-if="siblingCycles.length > 0 && units.length > 0"
            v-bind="storeGradeTransfer.form([teamSlug, farmId, cycleId])"
            class="grid gap-4"
            reset-on-success
            v-slot="{ errors, processing }"
            @success="emit('update:open', false)"
        >
            <div class="grid gap-1.5">
                <Label for="grade-date">Transfer date</Label>
                <Input
                    id="grade-date"
                    name="transfer_date"
                    type="date"
                    :default-value="today"
                    required
                />
                <InputError :message="errors.transfer_date" />
            </div>

            <div class="grid gap-1.5">
                <Label for="grade-count">Number of fish graded out</Label>
                <Input
                    id="grade-count"
                    name="count"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    required
                />
                <InputError :message="errors.count" />
            </div>

            <div class="grid gap-1.5">
                <Label for="grade-target-cycle">Receiving batch / cycle</Label>
                <select
                    id="grade-target-cycle"
                    name="target_cycle_id"
                    class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    required
                >
                    <option
                        v-for="target in siblingCycles"
                        :key="target.id"
                        :value="target.id"
                    >
                        {{ target.name }}
                    </option>
                </select>
                <InputError :message="errors.target_cycle_id" />
            </div>

            <div class="grid gap-1.5">
                <Label for="grade-target-unit">Destination pond</Label>
                <select
                    id="grade-target-unit"
                    name="target_unit_id"
                    class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    required
                >
                    <option
                        v-for="unit in units"
                        :key="unit.id"
                        :value="unit.id"
                    >
                        {{ unit.name }}
                    </option>
                </select>
                <InputError :message="errors.target_unit_id" />
            </div>

            <div class="grid gap-1.5">
                <Label for="grade-avg-weight">Sample avg weight (g)</Label>
                <Input
                    id="grade-avg-weight"
                    name="average_weight_g"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.1"
                />
                <InputError :message="errors.average_weight_g" />
            </div>

            <div class="grid gap-1.5">
                <Label for="grade-notes">Notes</Label>
                <Input
                    id="grade-notes"
                    name="notes"
                    placeholder="e.g. Week 3 grading of shooters"
                />
                <InputError :message="errors.notes" />
            </div>

            <Button
                type="submit"
                :disabled="processing"
                class="min-h-11 w-full sm:min-h-9 sm:w-auto"
            >
                Record graded transfer
            </Button>
        </Form>
        <p v-else class="text-sm text-muted-foreground">
            Create another receiving pond cycle on this farm first to transfer
            graded fish into it.
        </p>
    </SlideOverForm>
</template>
