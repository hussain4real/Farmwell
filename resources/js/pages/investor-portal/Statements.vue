<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CalendarRange, FileSpreadsheet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import PageHero from '@/components/farm/PageHero.vue';
import InputError from '@/components/InputError.vue';
import ReportDownload from '@/components/ReportDownload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useQueuedPolling } from '@/composables/useQueuedPolling';
import { formatInvestorDate } from '@/lib/investor-portal';
import { index as portal } from '@/routes/investor-portal';
import { download, store } from '@/routes/investor-statements';
import type { Team } from '@/types';
import type { ReportGeneration } from '@/types/report';

const props = defineProps<{
    agreement: {
        id: number;
        title: string;
        currency: string;
        farmName?: string;
        productionCycleName?: string | null;
        startsOn?: string | null;
        endsOn?: string | null;
    };
    statements: {
        data: (ReportGeneration & {
            id: number;
            period_start: string;
            period_end: string;
            created_at: string;
        })[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

useQueuedPolling('statements', () =>
    props.statements.data.some((item) => item.status === 'queued'),
);

const page = usePage();
const slug = computed(() => (page.props.currentTeam as Team).slug);

function toIsoDate(date: Date): string {
    return date.toISOString().slice(0, 10);
}

const now = new Date();
const defaultStart = toIsoDate(
    new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), 1)),
);
const defaultEnd = toIsoDate(now);

const periodStart = ref(defaultStart);
const periodEnd = ref(defaultEnd);

function applyPreset(
    preset: 'this_month' | 'last_month' | 'last_90' | 'agreement',
): void {
    const today = new Date();

    if (preset === 'this_month') {
        periodStart.value = toIsoDate(
            new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1)),
        );
        periodEnd.value = toIsoDate(today);

        return;
    }

    if (preset === 'last_month') {
        const start = new Date(
            Date.UTC(today.getUTCFullYear(), today.getUTCMonth() - 1, 1),
        );
        const end = new Date(
            Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 0),
        );
        periodStart.value = toIsoDate(start);
        periodEnd.value = toIsoDate(end);

        return;
    }

    if (preset === 'last_90') {
        const start = new Date(today);
        start.setUTCDate(start.getUTCDate() - 89);
        periodStart.value = toIsoDate(start);
        periodEnd.value = toIsoDate(today);

        return;
    }

    if (props.agreement.startsOn) {
        periodStart.value = props.agreement.startsOn;
        periodEnd.value = props.agreement.endsOn ?? toIsoDate(today);
    }
}
</script>

<template>
    <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
        <Head title="Investor statements" />

        <PageHero
            eyebrow="Investor Reporting"
            title="Investor statements"
            :description="`${agreement.title}${agreement.farmName ? ` · ${agreement.farmName}` : ''}${agreement.productionCycleName ? ` · ${agreement.productionCycleName}` : ''}`"
        >
            <template #actions>
                <Button
                    variant="secondary"
                    as-child
                    class="min-h-11 sm:min-h-9"
                >
                    <Link
                        :href="
                            portal(slug, {
                                query: { agreement: agreement.id },
                            })
                        "
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Back to investor portal
                    </Link>
                </Button>
            </template>
        </PageHero>

        <section class="space-y-4 rounded-2xl border bg-card p-5 shadow-xs">
            <div class="space-y-1">
                <h2 class="text-base font-semibold">Create a statement</h2>
                <p class="text-sm text-muted-foreground">
                    Select a period to report approved funding, expenses,
                    harvests and returns for this agreement in
                    {{ agreement.currency }}.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                >
                    <CalendarRange class="size-3.5" aria-hidden="true" />
                    Quick range:
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="applyPreset('this_month')"
                >
                    This month
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="applyPreset('last_month')"
                >
                    Last month
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="applyPreset('last_90')"
                >
                    Last 90 days
                </Button>
                <Button
                    v-if="agreement.startsOn"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="applyPreset('agreement')"
                >
                    Agreement period
                </Button>
            </div>

            <Form
                v-bind="store.form([slug, agreement.id])"
                v-slot="{ errors, processing }"
                class="grid gap-4 sm:grid-cols-2"
            >
                <div class="space-y-2">
                    <Label for="period_start">From</Label>
                    <Input
                        id="period_start"
                        v-model="periodStart"
                        name="period_start"
                        type="date"
                        required
                    />
                    <InputError :message="errors.period_start" />
                </div>
                <div class="space-y-2">
                    <Label for="period_end">Through</Label>
                    <Input
                        id="period_end"
                        v-model="periodEnd"
                        name="period_end"
                        type="date"
                        required
                    />
                    <InputError :message="errors.period_end" />
                </div>
                <label class="flex items-start gap-2.5 text-sm sm:col-span-2">
                    <input
                        type="checkbox"
                        name="include_summary"
                        value="1"
                        class="mt-1 size-4 rounded border"
                    />
                    <span>
                        Include the latest reviewed and published AI progress
                        update if all its activities fall within this period.
                    </span>
                </label>
                <InputError :message="errors.include_summary" />
                <div class="sm:col-span-2">
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="min-h-11 sm:min-h-9"
                    >
                        Generate statement PDF
                    </Button>
                </div>
            </Form>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-semibold">Saved statements</h2>
            <div v-if="statements.data.length" class="space-y-3">
                <div
                    v-for="item in statements.data"
                    :key="item.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4 shadow-xs"
                >
                    <div>
                        <p class="font-medium">
                            {{ formatInvestorDate(item.period_start) }} through
                            {{ formatInvestorDate(item.period_end) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Statement #{{ item.id }} · Created
                            {{ formatInvestorDate(item.created_at) }}
                        </p>
                    </div>
                    <ReportDownload
                        :report="item"
                        :href="download.url([slug, agreement.id, item.id])"
                        format="PDF"
                    />
                </div>
            </div>
            <EmptyState
                v-else
                :icon="FileSpreadsheet"
                title="No saved statements yet"
                description="Statements you generate will appear here as downloadable PDFs."
            />
            <nav
                v-if="statements.prev_page_url || statements.next_page_url"
                class="flex gap-4 text-sm underline"
                aria-label="Statement pages"
            >
                <Link
                    v-if="statements.prev_page_url"
                    :href="statements.prev_page_url"
                    >Previous</Link
                >
                <Link
                    v-if="statements.next_page_url"
                    :href="statements.next_page_url"
                    >Next</Link
                >
            </nav>
        </section>
    </main>
</template>
