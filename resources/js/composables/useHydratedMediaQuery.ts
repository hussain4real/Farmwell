import { useMediaQuery, useMounted } from '@vueuse/core';
import { computed } from 'vue';

export function useHydratedMediaQuery(query: string) {
    const mounted = useMounted();
    const matches = useMediaQuery(query);

    return computed(() => mounted.value && matches.value);
}
