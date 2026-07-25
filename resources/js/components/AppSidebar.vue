<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ClipboardList,
    FolderGit2,
    Handshake,
    Landmark,
    LayoutGrid,
    ListTodo,
    MessageSquare,
    ShieldCheck,
    Sprout,
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
import { index as financeIndex } from '@/routes/finance';
import { index as harvestsIndex } from '@/routes/harvests';
import { index as investorPortalIndex } from '@/routes/investor-portal';
import { index as investorsIndex } from '@/routes/investors';
import { index as investorApprovalsIndex } from '@/routes/investors/approvals';
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
const financeUrl = computed(() =>
    page.props.currentTeam
        ? financeIndex(page.props.currentTeam.slug).url
        : '/',
);
const harvestsUrl = computed(() =>
    page.props.currentTeam
        ? harvestsIndex(page.props.currentTeam.slug).url
        : '/',
);
const investorsUrl = computed(() =>
    page.props.currentTeam
        ? investorsIndex(page.props.currentTeam.slug).url
        : '/',
);
const investorApprovalsUrl = computed(() =>
    page.props.currentTeam
        ? investorApprovalsIndex(page.props.currentTeam.slug).url
        : '/',
);
const investorPortalUrl = computed(() =>
    page.props.currentTeam
        ? investorPortalIndex(page.props.currentTeam.slug).url
        : '/',
);
const currentPermissions = computed(() => page.props.currentTeamPermissions);
const investorOnly = computed(
    () =>
        currentPermissions.value?.canViewInvestorPortal === true &&
        currentPermissions.value.canViewFarmOperations !== true &&
        currentPermissions.value.canViewFinance !== true &&
        currentPermissions.value.canViewInvestorAgreements !== true,
);
const homeUrl = computed(() =>
    investorOnly.value ? investorPortalUrl.value : dashboardUrl.value,
);

const mainNavItems = computed<NavItem[]>(() => {
    const permissions = currentPermissions.value;

    if (investorOnly.value) {
        return [
            {
                title: 'Investor Portal',
                href: investorPortalUrl.value,
                icon: Handshake,
            },
        ];
    }

    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl.value,
            icon: LayoutGrid,
        },
    ];

    if (permissions?.canViewFarmOperations) {
        items.push(
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
        );
    }

    if (permissions?.canViewFinance) {
        items.push({
            title: 'Finance',
            href: financeUrl.value,
            icon: Landmark,
        });
    }

    if (permissions?.canViewFarmOperations || permissions?.canViewFinance) {
        items.push({
            title: 'Harvests',
            href: harvestsUrl.value,
            icon: Sprout,
        });
    }

    if (permissions?.canViewInvestorAgreements) {
        items.push({
            title: 'Investors',
            href: investorsUrl.value,
            icon: Handshake,
        });
    }

    if (permissions?.canViewApprovalRequests) {
        items.push({
            title: 'Approvals',
            href: investorApprovalsUrl.value,
            icon: ShieldCheck,
        });
    }

    return items;
});

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
                        <Link :href="homeUrl">
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
