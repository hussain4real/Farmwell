<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    ClipboardList,
    Handshake,
    HeartPulse,
    LayoutGrid,
    Package,
    Sprout,
    Tractor,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { index as farmsIndex } from '@/routes/farms';
import { index as fieldDiaryIndex } from '@/routes/field-diary';
import { index as healthIndex } from '@/routes/health';
import { index as inventoryIndex } from '@/routes/inventory';
import { index as investorPortalIndex } from '@/routes/investor-portal';
import { index as myInvestmentsIndex } from '@/routes/my-investments';

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

    if (investorOnly.value) {
        const items = [
            {
                key: 'portfolio',
                label: 'Portfolio',
                href: myInvestmentsIndex().url,
                icon: LayoutGrid,
            },
        ];

        if (slug) {
            items.push(
                {
                    key: 'portal',
                    label: 'Portal',
                    href: investorPortalIndex(slug).url,
                    icon: Handshake,
                },
                {
                    key: 'decisions',
                    label: 'Decisions',
                    href: investorPortalIndex(slug, {
                        query: { tab: 'decisions' },
                    }).url,
                    icon: ClipboardCheck,
                },
                {
                    key: 'updates',
                    label: 'Updates',
                    href: investorPortalIndex(slug, {
                        query: { tab: 'updates' },
                    }).url,
                    icon: Sprout,
                },
            );
        }

        return items;
    }

    if (!slug) {
        return [];
    }

    return [
        {
            key: 'home',
            label: 'Home',
            href: dashboard(slug).url,
            icon: LayoutGrid,
        },
        {
            key: 'farms',
            label: 'Farms',
            href: farmsIndex(slug).url,
            icon: Tractor,
        },
        {
            key: 'diary',
            label: 'Diary',
            href: fieldDiaryIndex(slug).url,
            icon: ClipboardList,
        },
        {
            key: 'stock',
            label: 'Stock',
            href: inventoryIndex(slug).url,
            icon: Package,
        },
        {
            key: 'health',
            label: 'Health',
            href: healthIndex(slug).url,
            icon: HeartPulse,
        },
    ];
});

const gridColumnsClass = computed(() => {
    if (navItems.value.length === 1) {
        return 'grid-cols-1';
    }

    if (navItems.value.length === 4) {
        return 'grid-cols-4';
    }

    return 'grid-cols-5';
});

const currentTab = computed(() => {
    const queryString = page.url.split('?')[1]?.split('#')[0] ?? '';

    return new URLSearchParams(queryString).get('tab');
});

const isActiveItem = (
    item: { key: string; href: string },
    index: number,
): boolean => {
    if (investorOnly.value) {
        if (item.key === 'portfolio') {
            return isCurrentUrl(item.href);
        }

        if (item.key === 'decisions' || item.key === 'updates') {
            return (
                page.url.includes('/investor-portal') &&
                currentTab.value === item.key
            );
        }

        return (
            page.url.includes('/investor-portal') &&
            currentTab.value !== 'decisions' &&
            currentTab.value !== 'updates'
        );
    }

    if (index === 0) {
        return isCurrentUrl(item.href);
    }

    return page.url === item.href || page.url.startsWith(`${item.href}/`);
};
</script>

<template>
    <nav
        v-if="navItems.length > 0"
        aria-label="Mobile quick navigation"
        class="fixed inset-x-0 bottom-0 z-40 grid border-t bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden"
        :class="gridColumnsClass"
    >
        <Link
            v-for="(item, index) in navItems"
            :key="item.key"
            :href="item.href"
            :aria-current="isActiveItem(item, index) ? 'page' : undefined"
            class="relative flex min-h-14 flex-col items-center justify-center gap-1 py-2 text-[11px] font-medium transition-colors"
            :class="
                isActiveItem(item, index)
                    ? 'text-primary'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <span
                v-if="isActiveItem(item, index)"
                class="absolute inset-x-3 top-0 h-0.5 rounded-b-full bg-primary"
            />
            <component :is="item.icon" class="size-5" />
            <span>{{ item.label }}</span>
        </Link>
    </nav>
</template>
