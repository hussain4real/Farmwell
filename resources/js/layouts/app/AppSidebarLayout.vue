<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import MobileQuickActionBar from '@/components/farm/MobileQuickActionBar.vue';
import OfflineStatusBar from '@/components/farm/OfflineStatusBar.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="overflow-x-hidden pb-[calc(5.25rem+env(safe-area-inset-bottom))] md:pb-0"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <OfflineStatusBar />
            <slot />
        </AppContent>
        <MobileQuickActionBar />
        <Toaster />
    </AppShell>
</template>
