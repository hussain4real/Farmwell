<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ClipboardList,
    FolderGit2,
    LayoutGrid,
    ListTodo,
    MessageSquare,
    Tractor,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as farmTasksIndex } from '@/routes/farm-tasks';
import { index as farmsIndex } from '@/routes/farms';
import { index as fieldDiaryIndex } from '@/routes/field-diary';
import { index as whatsappIntakesIndex } from '@/routes/whatsapp-intakes';
import type { NavItem } from '@/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);
const farmsUrl = computed(() =>
    page.props.currentTeam ? farmsIndex(page.props.currentTeam.slug).url : '/',
);
const fieldDiaryUrl = computed(() =>
    page.props.currentTeam
        ? fieldDiaryIndex(page.props.currentTeam.slug).url
        : '/',
);
const farmTasksUrl = computed(() =>
    page.props.currentTeam
        ? farmTasksIndex(page.props.currentTeam.slug).url
        : '/',
);
const whatsappIntakesUrl = computed(() =>
    page.props.currentTeam
        ? whatsappIntakesIndex(page.props.currentTeam.slug).url
        : '/',
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    {
        title: 'Farms',
        href: farmsUrl.value,
        icon: Tractor,
    },
    {
        title: 'Field Diary',
        href: fieldDiaryUrl.value,
        icon: ClipboardList,
    },
    {
        title: 'Tasks',
        href: farmTasksUrl.value,
        icon: ListTodo,
    },
    {
        title: 'WhatsApp Intake',
        href: whatsappIntakesUrl.value,
        icon: MessageSquare,
    },
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
