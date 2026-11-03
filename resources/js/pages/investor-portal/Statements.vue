<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as portal } from '@/routes/investor-portal';
import { download, store } from '@/routes/investor-statements';
import type { Team } from '@/types';

defineProps<{
    agreement: { id: number; title: string; currency: string };
    statements: {
        data: {
            id: number;
            period_start: string;
            period_end: string;
            created_at: string;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const page = usePage();
const slug = computed(() => (page.props.currentTeam as Team).slug);
</script>

<template>
    <main class="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-8">
        <Head title="Investor statements" />
        <Link :href="portal(slug)" class="text-sm underline"
            >Back to investor portal</Link
        >
        <Heading title="Investor statements" :description="agreement.title" />
        <section class="space-y-4 rounded-xl border bg-card p-5">
            <h2 class="font-semibold">Create a statement</h2>
            <p class="text-sm text-muted-foreground">
                Select a period to report approved funding, expenses, harvests
                and returns for this agreement in {{ agreement.currency }}.
            </p>
            <Form
                v-bind="store.form([slug, agreement.id])"
                v-slot="{ errors, processing }"
                class="flex flex-wrap items-start gap-4"
            >
                <div class="space-y-2">
                    <Label for="period_start">From</Label
                    ><Input
                        id="period_start"
                        name="period_start"
                        type="date"
                        required
                    /><InputError :message="errors.period_start" />
                </div>
                <div class="space-y-2">
                    <Label for="period_end">Through</Label
                    ><Input
                        id="period_end"
                        name="period_end"
                        type="date"
                        required
                    /><InputError :message="errors.period_end" />
                </div>
                <Button type="submit" :disabled="processing" class="self-end"
                    >Generate statement</Button
                >
            </Form>
        </section>
        <section class="space-y-3">
            <h2 class="font-semibold">Saved statements</h2>
            <p
                v-if="!statements.data.length"
                class="text-sm text-muted-foreground"
            >
                Statements you generate will appear here.
            </p>
            <div
                v-for="item in statements.data"
                :key="item.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
            >
                <div>
                    <p class="font-medium">
                        {{ item.period_start }} through {{ item.period_end }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Statement #{{ item.id }}
                    </p>
                </div>
                <a
                    :href="download.url([slug, agreement.id, item.id])"
                    class="text-sm underline"
                    >Download PDF</a
                >
            </div>
            <nav
                class="flex gap-4 text-sm underline"
                aria-label="Statement pages"
            >
                <Link
                    v-if="statements.prev_page_url"
                    :href="statements.prev_page_url"
                    >Previous</Link
                ><Link
                    v-if="statements.next_page_url"
                    :href="statements.next_page_url"
                    >Next</Link
                >
            </nav>
        </section>
    </main>
</template>
