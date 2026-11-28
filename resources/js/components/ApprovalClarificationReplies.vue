<script setup lang="ts">
import { formatInvestorDate } from '@/lib/investor-portal';
import type { InvestorApprovalRequest } from '@/types';

defineProps<{
    replies?: InvestorApprovalRequest['clarificationReplies'];
}>();
</script>

<template>
    <div v-if="replies?.length" class="space-y-2 rounded-lg border p-3">
        <p class="text-xs font-medium text-muted-foreground">
            Clarification replies
        </p>
        <ul class="space-y-3">
            <li v-for="reply in replies" :key="reply.id">
                <p class="text-xs font-medium">
                    {{ reply.authorName ?? 'Farm team' }}
                    <time
                        v-if="reply.createdAt"
                        :datetime="reply.createdAt"
                        class="text-muted-foreground"
                    >
                        · {{ formatInvestorDate(reply.createdAt) }}
                    </time>
                </p>
                <p class="mt-1 break-words whitespace-pre-wrap">
                    {{ reply.body }}
                </p>
            </li>
        </ul>
    </div>
</template>
