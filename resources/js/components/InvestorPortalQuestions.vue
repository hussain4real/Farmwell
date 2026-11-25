<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { MessageSquare } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatInvestorDate } from '@/lib/investor-portal';
import { store as storeInvestorComment } from '@/routes/investor-comments';
import type { InvestorPortalAgreement } from '@/types';

defineProps<{ agreement: InvestorPortalAgreement; currentTeamSlug: string }>();
</script>

<template>
    <section class="space-y-3">
        <Heading
            variant="small"
            title="Questions"
            description="Send a question about this agreement to the farm team."
        />
        <div v-if="agreement.comments.length" class="space-y-3">
            <div
                v-for="comment in agreement.comments"
                :key="comment.id"
                class="rounded-lg border p-3 text-sm"
            >
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <p class="font-medium">{{ comment.authorName }}</p>
                    <time
                        v-if="comment.createdAt"
                        :datetime="comment.createdAt"
                        class="text-xs text-muted-foreground"
                        >{{ formatInvestorDate(comment.createdAt) }}</time
                    >
                </div>
                <p class="mt-1 break-words whitespace-pre-wrap">
                    {{ comment.body }}
                </p>
                <p class="mt-1 text-muted-foreground">
                    {{ comment.subjectLabel }}
                </p>
            </div>
        </div>

        <Form
            v-if="currentTeamSlug"
            v-bind="
                storeInvestorComment.form({
                    current_team: currentTeamSlug,
                    investor_agreement: agreement.id,
                })
            "
            class="grid gap-3"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label :for="`comment-${agreement.id}`"> Comment </Label>
                <textarea
                    rows="3"
                    maxlength="2000"
                    class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    :id="`comment-${agreement.id}`"
                    name="body"
                    required
                />
                <InputError :message="errors.body" />
            </div>
            <div>
                <Button :disabled="processing">
                    <MessageSquare class="mr-2 size-4" />
                    Send question
                </Button>
            </div>
        </Form>
    </section>
</template>
