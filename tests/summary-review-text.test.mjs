import assert from 'node:assert/strict';
import test from 'node:test';
import { effectScope, nextTick, ref } from 'vue';
import { useSummaryReviewText } from '../resources/js/composables/useSummaryReviewText.ts';

test('polling preserves review edits and initializes newly generated drafts', async () => {
    const summaries = ref([
        { id: 1, status: 'draft', final_text: null, generated_text: 'Original draft' },
        { id: 2, status: 'queued', final_text: null, generated_text: null },
    ]);
    const scope = effectScope();
    const texts = scope.run(() => useSummaryReviewText(() => summaries.value));

    assert.equal(texts.value[1], 'Original draft');
    texts.value[1] = 'Reviewer edit';
    summaries.value = [
        { id: 1, status: 'draft', final_text: null, generated_text: 'Original draft' },
        { id: 2, status: 'draft', final_text: null, generated_text: 'New draft' },
    ];
    await nextTick();
    assert.equal(texts.value[1], 'Reviewer edit');
    assert.equal(texts.value[2], 'New draft');
    scope.stop();
});
