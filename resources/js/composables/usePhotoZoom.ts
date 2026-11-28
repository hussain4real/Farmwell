import { computed, shallowRef } from 'vue';

type Point = { x: number; y: number };
type PhotoView = Point & { scale: number };
type Gesture =
    | { kind: 'pan'; start: Touch; view: PhotoView }
    | { kind: 'pinch'; center: Point; distance: number; view: PhotoView };

const fitted: PhotoView = { x: 0, y: 0, scale: 1 };
const maximumScale = 4;

function clamp(value: number, min: number, max: number) {
    return Math.min(max, Math.max(min, value));
}

export function usePhotoZoom(getImage: () => HTMLImageElement | null) {
    const view = shallowRef(fitted);
    const zoomed = computed(() => view.value.scale > 1);
    const canZoomIn = computed(() => view.value.scale < maximumScale);
    const transform = computed(() => {
        const { x, y, scale } = view.value;

        return zoomed.value
            ? `translate(${x}px, ${y}px) scale(${scale})`
            : undefined;
    });
    let gesture: Gesture | null = null;

    function reset() {
        view.value = fitted;
        gesture = null;
    }

    function setView(next: PhotoView) {
        const image = getImage();

        if (
            !image?.naturalWidth ||
            !image.naturalHeight ||
            next.scale <= 1.02
        ) {
            view.value = fitted;

            return;
        }

        const {
            clientWidth: width,
            clientHeight: height,
            naturalWidth,
            naturalHeight,
        } = image;
        const fit = Math.min(width / naturalWidth, height / naturalHeight);
        const maxX = Math.max(0, (naturalWidth * fit * next.scale - width) / 2);
        const maxY = Math.max(
            0,
            (naturalHeight * fit * next.scale - height) / 2,
        );

        view.value = {
            x: clamp(next.x, -maxX, maxX),
            y: clamp(next.y, -maxY, maxY),
            scale: next.scale,
        };
    }

    function zoomBy(multiplier: number) {
        const current = view.value;
        const scale = clamp(current.scale * multiplier, 1, maximumScale);
        const ratio = scale / current.scale;

        setView({ x: current.x * ratio, y: current.y * ratio, scale });
    }

    function panBy(x: number, y: number) {
        const current = view.value;

        setView({ scale: current.scale, x: current.x + x, y: current.y + y });
    }

    function pinchPoints(first: Touch, second: Touch, target: HTMLElement) {
        const { left, top, width, height } = target.getBoundingClientRect();

        return {
            center: {
                x: (first.clientX + second.clientX) / 2 - left - width / 2,
                y: (first.clientY + second.clientY) / 2 - top - height / 2,
            },
            distance: Math.max(
                1,
                Math.hypot(
                    second.clientX - first.clientX,
                    second.clientY - first.clientY,
                ),
            ),
        };
    }

    function onContactsChanged(event: TouchEvent) {
        const first = event.touches.item(0);
        const second = event.touches.item(1);

        if (!first) {
            gesture = null;
        } else if (
            second &&
            event.currentTarget instanceof HTMLElement &&
            event.cancelable
        ) {
            event.preventDefault();
            gesture = {
                kind: 'pinch',
                ...pinchPoints(first, second, event.currentTarget),
                view: view.value,
            };
        } else if (zoomed.value || gesture) {
            if (event.cancelable) {
                event.preventDefault();
            }

            gesture = { kind: 'pan', start: first, view: view.value };
        }
    }

    function onTouchMove(event: TouchEvent) {
        const first = event.touches.item(0);

        if (!gesture || !first || !event.cancelable) {
            return;
        }

        event.preventDefault();
        const second = event.touches.item(1);

        if (
            gesture.kind === 'pinch' &&
            second &&
            event.currentTarget instanceof HTMLElement
        ) {
            const { distance, center } = pinchPoints(
                first,
                second,
                event.currentTarget,
            );
            const scale = clamp(
                (gesture.view.scale * distance) / gesture.distance,
                1,
                maximumScale,
            );
            const ratio = scale / gesture.view.scale;

            // Keep the same image detail under the moving pinch midpoint.
            setView({
                scale,
                x: center.x - (gesture.center.x - gesture.view.x) * ratio,
                y: center.y - (gesture.center.y - gesture.view.y) * ratio,
            });
        } else if (gesture.kind === 'pan') {
            setView({
                scale: gesture.view.scale,
                x: gesture.view.x + first.clientX - gesture.start.clientX,
                y: gesture.view.y + first.clientY - gesture.start.clientY,
            });
        }
    }

    function onTouchCancel() {
        gesture = null;
    }

    return {
        zoomed,
        canZoomIn,
        transform,
        reset,
        zoomBy,
        panBy,
        onContactsChanged,
        onTouchMove,
        onTouchCancel,
    };
}
