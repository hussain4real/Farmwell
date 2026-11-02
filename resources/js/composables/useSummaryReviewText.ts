import { ref, watch } from 'vue';

type ReviewableSummary = {
    id: number;
    status: string;
    final_text: string | null;
    generated_text: string | null;
};

export function useSummaryReviewText(summaries: () => ReviewableSummary[]) {
    const texts = ref<Record<number, string>>({});

    watch(
        summaries,
        (items) => {
            for (const item of items) {
                if (item.status === 'draft' && !(item.id in texts.value)) {
                    texts.value[item.id] =
                        item.final_text ?? item.generated_text ?? '';
                }
            }
        },
        { immediate: true },
    );

    return texts;
}
