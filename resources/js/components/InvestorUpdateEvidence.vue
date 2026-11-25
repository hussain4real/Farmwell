<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Paperclip,
    X,
    ZoomIn,
    ZoomOut,
} from 'lucide-vue-next';
import { computed, nextTick, ref, useId, useTemplateRef, watch } from 'vue';
import { usePhotoZoom } from '@/composables/usePhotoZoom';
import type { InvestorActivityEvidence } from '@/types';

const props = defineProps<{ evidence: InvestorActivityEvidence[] }>();

const viewer = useTemplateRef<HTMLDialogElement>('viewer');
const track = useTemplateRef<HTMLDivElement>('track');
const titleId = useId();
const photoIndex = ref<number | null>(null);
const photos = computed(() =>
    props.evidence.filter(
        (file): file is InvestorActivityEvidence & { previewUrl: string } =>
            typeof file.previewUrl === 'string',
    ),
);
const photo = computed(() => photos.value[photoIndex.value ?? -1]);
const zoom = usePhotoZoom(
    () =>
        track.value?.children[photoIndex.value ?? -1]?.querySelector('img') ??
        null,
);
const { zoomed, canZoomIn, transform } = zoom;
watch(photoIndex, zoom.reset, { flush: 'sync' });

const panKeys = new Map([
    ['ArrowLeft', [64, 0]],
    ['ArrowRight', [-64, 0]],
    ['ArrowUp', [0, 64]],
    ['ArrowDown', [0, -64]],
]);

function handleTrackKeydown(event: KeyboardEvent) {
    const offset = panKeys.get(event.key);

    if (zoomed.value && offset) {
        event.preventDefault();
        zoom.panBy(offset[0], offset[1]);
    } else if (
        photoIndex.value !== null &&
        (event.key === 'ArrowLeft' || event.key === 'ArrowRight')
    ) {
        event.preventDefault();
        scrollToPhoto(photoIndex.value + (event.key === 'ArrowLeft' ? -1 : 1));
    }
}

function syncPhoto() {
    const scroller = track.value;

    if (!scroller) {
        return;
    }

    const bounds = scroller.getBoundingClientRect();
    const center = (bounds.left + bounds.right) / 2;
    const index = Array.from(scroller.children).findIndex((slide) => {
        const position = slide.getBoundingClientRect();

        return position.left <= center && position.right > center;
    });

    if (index >= 0) {
        photoIndex.value = index;
    }
}

const syncAfterScroll = useDebounceFn(syncPhoto, 120);

function handleScroll() {
    if (track.value && !('onscrollend' in track.value)) {
        void syncAfterScroll();
    }
}

function scrollToPhoto(index: number) {
    const scroller = track.value;
    const slide = scroller?.children[index];

    if (!scroller || !slide) {
        return;
    }

    scroller.scrollBy({
        left:
            slide.getBoundingClientRect().left -
            scroller.getBoundingClientRect().left,
        behavior: 'instant',
    });
    syncPhoto();
}

async function openPhoto(photoId: number) {
    const index = photos.value.findIndex((file) => file.id === photoId);

    if (index < 0) {
        return;
    }

    photoIndex.value = index;
    await nextTick();
    viewer.value?.showModal();
    scrollToPhoto(index);
}
</script>

<template>
    <div class="mt-3">
        <ul class="flex flex-wrap gap-2">
            <li
                v-for="file in evidence"
                :key="file.id"
                class="max-w-full min-w-0"
            >
                <button
                    v-if="file.previewUrl"
                    type="button"
                    aria-haspopup="dialog"
                    :aria-label="`View photo: ${file.caption ? `${file.caption} (${file.fileName})` : file.fileName}`"
                    class="inline-flex w-36 max-w-full flex-col overflow-hidden rounded-md border text-start text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    @click="openPhoto(file.id)"
                >
                    <img
                        :src="file.previewUrl"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        class="aspect-[4/3] w-full bg-muted object-cover"
                    />
                    <span class="px-2 py-1.5 break-all">{{
                        file.fileName
                    }}</span>
                </button>
                <a
                    v-else
                    :href="file.downloadUrl"
                    class="inline-flex max-w-full items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <Paperclip aria-hidden="true" class="size-4 shrink-0" />
                    <span class="break-all">{{ file.fileName }}</span>
                </a>
            </li>
        </ul>

        <dialog
            ref="viewer"
            :aria-labelledby="titleId"
            class="fixed inset-0 m-0 h-screen max-h-none w-full max-w-none overflow-hidden overscroll-contain bg-neutral-950 text-white backdrop:overflow-hidden backdrop:overscroll-contain backdrop:bg-black supports-[height:100dvh]:h-dvh"
            @close="photoIndex = null"
        >
            <div
                v-if="photo && photoIndex !== null"
                class="grid h-full grid-rows-[auto_minmax(0,1fr)_auto]"
            >
                <header
                    class="flex items-center justify-between gap-3 px-3 pt-[max(0.5rem,env(safe-area-inset-top))] pb-2 sm:px-6"
                >
                    <h2 :id="titleId" class="sr-only">Farm update photos</h2>
                    <form method="dialog">
                        <button
                            type="submit"
                            aria-label="Close photo viewer"
                            autofocus
                            class="inline-flex size-11 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2"
                        >
                            <X aria-hidden="true" class="size-5" />
                        </button>
                    </form>
                    <p
                        role="status"
                        class="text-sm text-neutral-200 tabular-nums"
                    >
                        Photo {{ photoIndex + 1 }} of {{ photos.length }}
                    </p>
                    <a
                        :href="photo.downloadUrl"
                        :download="photo.fileName"
                        class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white/10 px-3 text-sm font-medium hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        <Download aria-hidden="true" class="size-4" />
                        Download
                    </a>
                </header>

                <div
                    ref="track"
                    role="region"
                    aria-label="Shared photos"
                    aria-roledescription="carousel"
                    tabindex="0"
                    class="flex min-h-0 min-w-0 snap-x snap-mandatory [scrollbar-width:none] overflow-y-hidden overscroll-contain focus-visible:outline-2 focus-visible:-outline-offset-2"
                    :class="
                        zoomed
                            ? 'touch-none overflow-x-hidden'
                            : 'touch-pan-x overflow-x-auto'
                    "
                    @scroll.passive="handleScroll"
                    @scrollend="syncPhoto"
                    @keydown.self="handleTrackKeydown"
                    @touchstart="zoom.onContactsChanged"
                    @touchmove="zoom.onTouchMove"
                    @touchend="zoom.onContactsChanged"
                    @touchcancel="zoom.onTouchCancel"
                >
                    <div
                        v-for="(image, index) in photos"
                        :key="image.id"
                        role="group"
                        aria-roledescription="slide"
                        :aria-label="`${index + 1} of ${photos.length}`"
                        class="grid w-full shrink-0 snap-center snap-always place-items-center overflow-hidden"
                    >
                        <img
                            :src="image.previewUrl"
                            :alt="image.caption || image.fileName"
                            :loading="index === photoIndex ? 'eager' : 'lazy'"
                            decoding="async"
                            draggable="false"
                            class="h-full min-h-0 w-full object-contain select-none"
                            :style="
                                index === photoIndex ? { transform } : undefined
                            "
                        />
                    </div>
                </div>

                <footer
                    class="space-y-1 px-3 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] text-center sm:px-6"
                >
                    <div
                        class="flex min-w-0 items-center justify-between gap-3"
                    >
                        <p
                            class="truncate text-start text-sm text-neutral-200"
                            :title="photo.caption || photo.fileName"
                        >
                            {{ photo.caption || photo.fileName }}
                        </p>
                        <div
                            role="group"
                            aria-label="Photo zoom"
                            class="flex shrink-0 gap-2"
                        >
                            <button
                                type="button"
                                aria-label="Zoom out"
                                :aria-disabled="!zoomed"
                                class="inline-flex size-11 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 focus-visible:outline-2 aria-disabled:opacity-40"
                                @click="zoom.zoomBy(1 / 1.5)"
                            >
                                <ZoomOut aria-hidden="true" class="size-5" />
                            </button>
                            <button
                                type="button"
                                aria-label="Zoom in"
                                :aria-disabled="!canZoomIn"
                                class="inline-flex size-11 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 focus-visible:outline-2 aria-disabled:opacity-40"
                                @click="zoom.zoomBy(1.5)"
                            >
                                <ZoomIn aria-hidden="true" class="size-5" />
                            </button>
                        </div>
                    </div>
                    <nav
                        v-if="photos.length > 1"
                        aria-label="Photo navigation"
                        class="flex items-center justify-center gap-2"
                    >
                        <button
                            type="button"
                            aria-label="Previous photo"
                            :aria-disabled="photoIndex === 0"
                            class="inline-flex size-11 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 focus-visible:outline-2 aria-disabled:opacity-40"
                            @click="scrollToPhoto(photoIndex - 1)"
                        >
                            <ChevronLeft aria-hidden="true" class="size-5" />
                        </button>
                        <button
                            v-for="(image, index) in photos"
                            :key="image.id"
                            type="button"
                            :aria-label="`Go to photo ${index + 1}`"
                            :aria-current="
                                index === photoIndex ? 'true' : undefined
                            "
                            class="group grid size-9 place-items-center rounded-full focus-visible:outline-2"
                            @click="scrollToPhoto(index)"
                        >
                            <span
                                aria-hidden="true"
                                class="size-2 rounded-full bg-white/35 group-hover:bg-white/65 group-aria-[current=true]:bg-white"
                            />
                        </button>
                        <button
                            type="button"
                            aria-label="Next photo"
                            :aria-disabled="photoIndex === photos.length - 1"
                            class="inline-flex size-11 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 focus-visible:outline-2 aria-disabled:opacity-40"
                            @click="scrollToPhoto(photoIndex + 1)"
                        >
                            <ChevronRight aria-hidden="true" class="size-5" />
                        </button>
                    </nav>
                </footer>
            </div>
        </dialog>
    </div>
</template>
