<script setup lang="ts">
import EvidenceGallery from '@/components/farm/EvidenceGallery.vue';
import type { InvestorActivityEvidence } from '@/types';

defineProps<{ evidence: InvestorActivityEvidence[] }>();
</script>

<template>
    <div class="mt-3">
        <EvidenceGallery :items="evidence" dialog-title="Farm update photos" />
    </div>
</template>
