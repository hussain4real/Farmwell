<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ReportDownload from '@/components/ReportDownload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useQueuedPolling } from '@/composables/useQueuedPolling';
import { download, store } from '@/routes/finance/budget-exports';
import { index as budgets } from '@/routes/finance/budgets';
import type { Team } from '@/types';
import type { ReportGeneration } from '@/types/report';

const props = defineProps<{
    budget: {
        id: number;
        name: string;
        currency: string;
        start: string | null;
        end: string | null;
    };
    exports: {
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
useQueuedPolling('exports', () =>
    props.exports.data.some((item) => item.status === 'queued'),
);
const page = usePage();
const slug = computed(() => (page.props.currentTeam as Team).slug);
</script>

<template>
    <main class="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-8">
        <Head title="Budget variance exports" />
        <Link :href="budgets(slug)" class="text-sm underline"
            >Back to budgets</Link
        >
        <Heading title="Budget variance exports" :description="budget.name" />
        <section class="space-y-4 rounded-xl border bg-card p-5">
            <h2 class="font-semibold">Create a workbook</h2>
            <p class="text-sm text-muted-foreground">
                Compare the full budget with expenses recorded in the selected
                period, in {{ budget.currency }}. Includes draft, submitted,
                approved and reconciled expenses. Each workbook can include up
                to 1,000 expenses.
            </p>
            <Form
                v-bind="store.form([slug, budget.id])"
                v-slot="{ errors, processing }"
                class="flex flex-wrap items-start gap-4"
            >
                <div class="space-y-2">
                    <Label for="period_start">From</Label
                    ><Input
                        id="period_start"
                        name="period_start"
                        :default-value="budget.start ?? undefined"
                        type="date"
                        required
                    /><InputError :message="errors.period_start" />
                </div>
                <div class="space-y-2">
                    <Label for="period_end">Through</Label
                    ><Input
                        id="period_end"
                        name="period_end"
                        :default-value="budget.end ?? undefined"
                        type="date"
                        required
                    /><InputError :message="errors.period_end" />
                </div>
                <Button type="submit" :disabled="processing" class="self-end"
                    >Generate workbook</Button
                >
            </Form>
        </section>
        <section class="space-y-3">
            <h2 class="font-semibold">Saved exports</h2>
            <p
                v-if="!exports.data.length"
                class="text-sm text-muted-foreground"
            >
                Workbooks you generate will appear here.
            </p>
            <div
                v-for="item in exports.data"
                :key="item.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
            >
                <div>
                    <p class="font-medium">
                        {{ item.period_start }} through {{ item.period_end }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Export #{{ item.id }}
                    </p>
                </div>
                <ReportDownload
                    :report="item"
                    :href="download.url([slug, budget.id, item.id])"
                    format="XLSX"
                />
            </div>
            <nav class="flex gap-4 text-sm underline" aria-label="Export pages">
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
