<script setup lang="ts">
import type { ReportGeneration } from '@/types/report';

defineProps<{
    report: ReportGeneration;
    href: string;
    format: 'PDF' | 'XLSX';
}>();
</script>

<template>
    <div class="text-sm" aria-live="polite">
        <a v-if="report.status === 'ready'" :href="href" class="underline"
            >Download {{ format }}</a
        >
        <span
            v-else-if="report.status === 'queued'"
            class="text-muted-foreground"
            >Preparing {{ format }}…</span
        >
        <span v-else class="text-destructive">{{
            report.failure_message ?? 'Generation failed.'
        }}</span>
    </div>
</template>
