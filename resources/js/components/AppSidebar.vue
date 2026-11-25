<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ClipboardList,
    FileText,
    Handshake,
    HeartPulse,
    Landmark,
    LayoutGrid,
    ListTodo,
    MessageSquare,
    Package,
    PlusCircle,
    ShieldCheck,
    Sprout,
    Tractor,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { index as aiSummariesIndex } from '@/routes/ai-summaries';
import { index as farmTasksIndex } from '@/routes/farm-tasks';
import { index as farmsIndex } from '@/routes/farms';
import { index as fieldDiaryIndex } from '@/routes/field-diary';
import { index as financeIndex } from '@/routes/finance';
import { index as harvestsIndex } from '@/routes/harvests';
import { index as healthIndex } from '@/routes/health';
import { index as inventoryIndex } from '@/routes/inventory';
import { index as investorPortalIndex } from '@/routes/investor-portal';
import { index as investorsIndex } from '@/routes/investors';
import { index as investorApprovalsIndex } from '@/routes/investors/approvals';
import { index as myInvestments } from '@/routes/my-investments';
import { index as onboardingIndex } from '@/routes/onboarding';
import { index as platformIndex } from '@/routes/platform';
import { index as whatsappIntakesIndex } from '@/routes/whatsapp-intakes';
import type { NavItem } from '@/types';

const page = usePage();

const slug = computed(() => page.props.currentTeam?.slug ?? '');
const currentPermissions = computed(() => page.props.currentTeamPermissions);
const investorMode = computed(
    () => page.props.currentTeamFeatures?.investorMode === true,
);

const investorOnly = computed(
    () =>
        currentPermissions.value?.canViewInvestorPortal === true &&
        currentPermissions.value.canViewFarmOperations !== true &&
        currentPermissions.value.canViewFinance !== true &&
        currentPermissions.value.canViewInvestorAgreements !== true,
);

const dashboardUrl = computed(() =>
    slug.value ? dashboard(slug.value).url : '/',
);
const onboardingUrl = computed(() =>
    slug.value ? onboardingIndex(slug.value).url : '/',
);
const farmsUrl = computed(() =>
    slug.value ? farmsIndex(slug.value).url : '/',
);
const fieldDiaryUrl = computed(() =>
    slug.value ? fieldDiaryIndex(slug.value).url : '/',
);
const farmTasksUrl = computed(() =>
    slug.value ? farmTasksIndex(slug.value).url : '/',
);
const inventoryUrl = computed(() =>
    slug.value ? inventoryIndex(slug.value).url : '/',
);
const healthUrl = computed(() =>
    slug.value ? healthIndex(slug.value).url : '/',
);
const whatsappIntakesUrl = computed(() =>
    slug.value ? whatsappIntakesIndex(slug.value).url : '/',
);
const financeUrl = computed(() =>
    slug.value ? financeIndex(slug.value).url : '/',
);
const harvestsUrl = computed(() =>
    slug.value ? harvestsIndex(slug.value).url : '/',
);
const investorsUrl = computed(() =>
    slug.value ? investorsIndex(slug.value).url : '/',
);
const investorApprovalsUrl = computed(() =>
    slug.value ? investorApprovalsIndex(slug.value).url : '/',
);
const investorPortalUrl = computed(() =>
    slug.value ? investorPortalIndex(slug.value).url : '/',
);
const aiSummariesUrl = computed(() =>
    slug.value ? aiSummariesIndex(slug.value).url : '/',
);

const homeUrl = computed(() =>
    investorOnly.value ? myInvestments.url() : dashboardUrl.value,
);
const investmentsNavItem: NavItem = {
    title: 'My Investments',
    href: myInvestments.url(),
    icon: LayoutGrid,
};

const mainNavItems = computed<NavItem[]>(() => {
    const permissions = currentPermissions.value;
    const access = page.props.platformAccess;

    if (access) {
        return [
            {
                title: 'Dashboard',
                href: dashboard(access.team_slug).url,
                icon: LayoutGrid,
            },
            {
                title: 'Field Diary',
                href: fieldDiaryIndex(access.team_slug).url,
                icon: ClipboardList,
            },
            {
                title: 'Finance',
                href: financeIndex(access.team_slug).url,
                icon: Landmark,
            },
            {
                title: 'Investors',
                href: investorsIndex(access.team_slug).url,
                icon: Handshake,
            },
            {
                title: 'Approvals',
                href: investorApprovalsIndex(access.team_slug).url,
                icon: ShieldCheck,
            },
        ];
    }

    if (investorOnly.value) {
        return [
            investmentsNavItem,
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

    if (page.props.teams?.some((team) => team.role === 'investor')) {
        items.push(investmentsNavItem);
    }

    if (permissions?.canViewFarmOperations) {
        items.push({
            title: 'Farms',
            href: farmsUrl.value,
            icon: Tractor,
        });
    }

    if (permissions?.canManageFarmOperations) {
        items.push({
            title: 'Onboard Farm',
            href: onboardingUrl.value,
            icon: PlusCircle,
        });
    }

    if (permissions?.canViewFarmOperations || permissions?.canManageFieldWork) {
        items.push(
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
                title: 'Stock & Feed',
                href: inventoryUrl.value,
                icon: Package,
            },
            {
                title: 'Health & Vaccines',
                href: healthUrl.value,
                icon: HeartPulse,
            },
        );
    }

    if (permissions?.canViewFarmOperations) {
        items.push({
            title: 'WhatsApp Intake',
            href: whatsappIntakesUrl.value,
            icon: MessageSquare,
        });
    }

    if (permissions?.canViewFarmOperations || permissions?.canViewFinance) {
        items.push({
            title: 'Harvests & Sales',
            href: harvestsUrl.value,
            icon: Sprout,
        });
    }

    if (permissions?.canViewFinance) {
        items.push({
            title: 'Finance',
            href: financeUrl.value,
            icon: Landmark,
        });
    }

    if (
        permissions?.canManageInvestorAgreements ||
        permissions?.canManageFarmOperations
    ) {
        items.push({
            title: 'AI Summaries',
            href: aiSummariesUrl.value,
            icon: FileText,
        });
    }

    if (investorMode.value && permissions?.canViewInvestorAgreements) {
        items.push({
            title: 'Investors',
            href: investorsUrl.value,
            icon: Handshake,
        });
    }

    if (investorMode.value && permissions?.canViewApprovalRequests) {
        items.push({
            title: 'Approvals',
            href: investorApprovalsUrl.value,
            icon: ShieldCheck,
        });
    }

    return items;
});
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
                    <p
                        v-if="page.props.platformAccess"
                        class="px-2 py-3 text-sm font-medium"
                    >
                        {{ page.props.platformAccess.team_name
                        }}<span class="block text-xs text-muted-foreground"
                            >Admin session · read only</span
                        >
                    </p>
                    <TeamSwitcher v-else />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="page.props.canManagePlatform"
                :items="[
                    {
                        title: 'Platform admin',
                        href: platformIndex().url,
                        icon: ShieldCheck,
                    },
                ]"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
