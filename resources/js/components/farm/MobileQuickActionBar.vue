<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ClipboardList,
    HeartPulse,
    LayoutGrid,
    Package,
    Tractor,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { index as farmsIndex } from '@/routes/farms';
import { index as fieldDiaryIndex } from '@/routes/field-diary';
import { index as healthIndex } from '@/routes/health';
import { index as inventoryIndex } from '@/routes/inventory';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const currentTeam = computed(() => page.props.currentTeam);
const permissions = computed(() => page.props.currentTeamPermissions);

const investorOnly = computed(
    () =>
        permissions.value?.canViewInvestorPortal === true &&
        permissions.value.canViewFarmOperations !== true &&
        permissions.value.canViewFinance !== true &&
        permissions.value.canViewInvestorAgreements !== true,
);

const navItems = computed(() => {
    const slug = currentTeam.value?.slug;

    if (!slug || investorOnly.value) {
        return [];
    }

    return [
        {
            label: 'Home',
            href: dashboard(slug).url,
            icon: LayoutGrid,
        },
        {
            label: 'Farms',
            href: farmsIndex(slug).url,
            icon: Tractor,
        },
        {
            label: 'Diary',
            href: fieldDiaryIndex(slug).url,
            icon: ClipboardList,
        },
        {
            label: 'Stock',
            href: inventoryIndex(slug).url,
            icon: Package,
        },
        {
            label: 'Health',
            href: healthIndex(slug).url,
            icon: HeartPulse,
        },
    ];
});
const isActiveItem = (href: string, index: number): boolean => {
    if (index === 0) {
        return isCurrentUrl(href);
    }

    return page.url === href || page.url.startsWith(`${href}/`);
};
</script>

<template>
    <nav
        v-if="navItems.length > 0"
        aria-label="Mobile quick navigation"
        class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden"
    >
        <Link
            v-for="(item, index) in navItems"
            :key="item.label"
            :href="item.href"
            :aria-current="isActiveItem(item.href, index) ? 'page' : undefined"
            class="relative flex min-h-14 flex-col items-center justify-center gap-1 py-2 text-[11px] font-medium transition-colors"
            :class="
                isActiveItem(item.href, index)
                    ? 'text-primary'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <span
                v-if="isActiveItem(item.href, index)"
                class="absolute inset-x-3 top-0 h-0.5 rounded-b-full bg-primary"
            />
            <component :is="item.icon" class="size-5" />
            <span>{{ item.label }}</span>
        </Link>
    </nav>
</template>
