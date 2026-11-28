<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ReportDownload from '@/components/ReportDownload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useQueuedPolling } from '@/composables/useQueuedPolling';
import { download, store } from '@/routes/activity-exports';
import { index as diary } from '@/routes/field-diary';
import type { Team } from '@/types';
import type { ReportGeneration } from '@/types/report';

type Option = { id: number; name: string };
const props = defineProps<{
    farms: Option[];
    commodities: Option[];
    units: (Option & { farm_id: number })[];
    phases: (Option & { farm_id: number })[];
    exports: {
        data: (ReportGeneration & {
            id: number;
            farm_id: number;
            period_start: string;
            period_end: string;
        })[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
useQueuedPolling('exports', () =>
    props.exports.data.some((item) => item.status === 'queued'),
);
const page = usePage();
const slug = computed(() => (page.props.currentTeam as Team).slug);
const farm = ref(props.farms[0]?.id);
const unit = ref('');
const phase = ref('');
watch(farm, () => {
    unit.value = '';
    phase.value = '';
});
</script>
<template>
    <main class="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-8">
        <Head title="Activity exports" />
        <Link :href="diary(slug)" class="text-sm underline"
            >Back to field diary</Link
        >
        <Heading
            title="Export activity log"
            description="Download up to 1,000 field diary entries per Excel workbook."
        />
        <section class="space-y-4 rounded-xl border bg-card p-5">
            <p v-if="!farms.length">
                Add a farm to start exporting activities.
            </p>
            <Form
                v-else
                v-bind="store.form(slug)"
                v-slot="{ errors, processing }"
                class="grid gap-4 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="farm">Farm</Label
                    ><select
                        id="farm"
                        name="farm_id"
                        v-model="farm"
                        required
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="item in farms"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option></select
                    ><InputError :message="errors.farm_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="activity_type">Activity type (optional)</Label
                    ><Input
                        id="activity_type"
                        name="activity_type"
                        placeholder="e.g. Planting"
                        maxlength="100"
                    /><InputError :message="errors.activity_type" />
                </div>
                <div class="grid gap-2">
                    <Label for="from">From</Label
                    ><Input
                        id="from"
                        name="period_start"
                        type="date"
                        required
                    /><InputError :message="errors.period_start" />
                </div>
                <div class="grid gap-2">
                    <Label for="through">Through</Label
                    ><Input
                        id="through"
                        name="period_end"
                        type="date"
                        required
                    /><InputError :message="errors.period_end" />
                </div>
                <div class="grid gap-2">
                    <Label for="unit">Production unit</Label
                    ><select
                        id="unit"
                        name="production_unit_id"
                        v-model="unit"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">All units</option>
                        <option
                            v-for="item in units.filter(
                                (i) => i.farm_id === farm,
                            )"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option></select
                    ><InputError :message="errors.production_unit_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="commodity">Commodity</Label
                    ><select
                        id="commodity"
                        name="commodity_id"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">All commodities</option>
                        <option
                            v-for="item in commodities"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option></select
                    ><InputError :message="errors.commodity_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase">Funding phase</Label
                    ><select
                        id="phase"
                        name="funding_phase_id"
                        v-model="phase"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">All activities</option>
                        <option
                            v-for="item in phases.filter(
                                (i) => i.farm_id === farm,
                            )"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option></select
                    ><InputError :message="errors.funding_phase_id" />
                    <p class="text-xs text-muted-foreground">
                        Selecting a phase includes only activities with an
                        expense linked to that phase.
                    </p>
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="self-end justify-self-start"
                    >Generate export</Button
                >
            </Form>
        </section>
        <section class="space-y-3">
            <h2 class="font-semibold">Your saved exports</h2>
            <p
                v-if="!exports.data.length"
                class="text-sm text-muted-foreground"
            >
                Your first export will appear here.
            </p>
            <div
                v-for="item in exports.data"
                :key="item.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
            >
                <div>
                    <p class="font-medium">
                        {{
                            farms.find((f) => f.id === item.farm_id)?.name ??
                            'Farm'
                        }}
                        · Export #{{ item.id }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ item.period_start }} through {{ item.period_end }}
                    </p>
                </div>
                <ReportDownload
                    :report="item"
                    :href="download.url([slug, item.id])"
                    format="XLSX"
                />
            </div>
            <nav aria-label="Export pages" class="flex gap-4 text-sm underline">
                <Link v-if="exports.prev_page_url" :href="exports.prev_page_url"
                    >Previous</Link
                ><Link
                    v-if="exports.next_page_url"
                    :href="exports.next_page_url"
                    >Next</Link
                >
            </nav>
        </section>
    </main>
</template>
