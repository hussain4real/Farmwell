<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bird,
    CheckCircle2,
    Fish,
    HeartPulse,
    Landmark,
    Package,
    Sprout,
} from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { isStandaloneApp } from '@/lib/installApp';
import { dashboard, login, register } from '@/routes';
import { index as investorPortal } from '@/routes/investor-portal';

const page = usePage();
const homeUrl = computed(() => {
    const team = page.props.currentTeam;

    if (!team) {
        return '/';
    }

    const permissions = page.props.currentTeamPermissions;

    return permissions?.canViewInvestorPortal &&
        !permissions.canViewFarmOperations &&
        !permissions.canViewFinance &&
        !permissions.canViewInvestorAgreements
        ? investorPortal(team.slug).url
        : dashboard(team.slug).url;
});

onMounted(() => {
    if (page.props.auth.user && page.props.currentTeam && isStandaloneApp()) {
        router.visit(homeUrl.value, { replace: true });
    }
});

const enterprises = [
    {
        title: 'Poultry Farming',
        badge: 'Broilers & Layers',
        icon: Bird,
        tone: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
        points: [
            'Live flock count, mortality & culls',
            'Feed conversion ratio (FCR) & egg lay rate',
            'Automated Lasota & Gumboro vaccine schedules',
        ],
    },
    {
        title: 'Crop Farming',
        badge: 'Grains & Vegetables',
        icon: Sprout,
        tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
        points: [
            'Field & plot growth stage progression',
            'Pest scouting, rainfall & irrigation logs',
            'Fertilizer/chemical inventory & yield per acre',
        ],
    },
    {
        title: 'Fish Farming',
        badge: 'Catfish & Tilapia',
        icon: Fish,
        tone: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-300',
        points: [
            'Pond water quality alerts (pH, DO, ammonia)',
            'Biomass sampling, pellet sizing & FCR',
            'Grading & shooter transfers between ponds',
        ],
    },
];
</script>

<template>
    <div
        class="min-h-screen bg-[#f6f8f4] text-[#142c22] dark:bg-background dark:text-foreground"
    >
        <Head title="Welcome" />
        <header
            class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6 sm:px-8"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-[#1b4d3e] text-white shadow-xs"
                >
                    <Sprout class="size-5" />
                </span>
                <div>
                    <span class="text-lg font-semibold tracking-tight">
                        Farmwell
                    </span>
                    <span
                        class="block text-[11px] font-medium text-muted-foreground"
                    >
                        Multi-Enterprise Farm OS
                    </span>
                </div>
            </div>
            <nav
                aria-label="Account"
                class="flex items-center gap-2 text-sm font-medium"
            >
                <Link
                    v-if="$page.props.auth.user"
                    :href="homeUrl"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#1b4d3e] px-4 py-2.5 text-white transition-colors hover:bg-[#256652]"
                >
                    Open workspace
                    <ArrowRight class="size-4" />
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-lg px-4 py-2.5 hover:bg-accent"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="rounded-lg bg-[#1b4d3e] px-4 py-2.5 text-white transition-colors hover:bg-[#256652]"
                    >
                        Start your farm
                    </Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl space-y-16 px-6 py-10 sm:px-8 md:py-16">
            <section
                class="grid gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-center"
            >
                <div class="space-y-6">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        Built for Poultry, Crop & Fish Farmers
                    </span>
                    <h1
                        class="text-4xl leading-[1.1] font-semibold tracking-tight sm:text-5xl"
                    >
                        Run your entire farm from stocking or planting to
                        harvest & profit.
                    </h1>
                    <p
                        class="max-w-xl text-base leading-7 text-muted-foreground"
                    >
                        Onboard your poultry houses, crop fields, or fish ponds
                        in minutes. Log daily feed, mortality, water quality,
                        vaccines, stock levels, and sales in one calm,
                        mobile-friendly workspace.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="
                                $page.props.auth.user ? homeUrl : register().url
                            "
                            class="inline-flex items-center gap-2 rounded-lg bg-[#1b4d3e] px-5 py-3 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-[#256652]"
                        >
                            {{
                                $page.props.auth.user
                                    ? 'Open your workspace'
                                    : 'Onboard your farm'
                            }}
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            v-if="!$page.props.auth.user"
                            :href="login()"
                            class="inline-flex items-center rounded-lg border bg-card px-5 py-3 text-sm font-semibold hover:bg-accent"
                        >
                            Log in
                        </Link>
                    </div>
                </div>

                <div
                    class="space-y-4 rounded-2xl border border-[#245c4b]/20 bg-gradient-to-br from-[#1b4d3e] to-[#13382c] p-7 text-white shadow-md"
                >
                    <h2 class="text-lg font-semibold">
                        Everything your farm team needs daily
                    </h2>
                    <div class="space-y-4 pt-2">
                        <div class="flex gap-3.5">
                            <Package
                                class="mt-0.5 size-5 shrink-0 text-emerald-300"
                            />
                            <div>
                                <p class="font-medium">
                                    Feed & input stock control
                                </p>
                                <p class="text-sm text-emerald-100/80">
                                    Auto-deduct feed and fertilizers straight
                                    from daily logs with low-stock alerts.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3.5">
                            <HeartPulse
                                class="mt-0.5 size-5 shrink-0 text-amber-300"
                            />
                            <div>
                                <p class="font-medium">
                                    Vaccination & biosecurity guardrails
                                </p>
                                <p class="text-sm text-emerald-100/80">
                                    Apply starter schedules and track medication
                                    withdrawal periods before harvest.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3.5">
                            <Landmark
                                class="mt-0.5 size-5 shrink-0 text-cyan-300"
                            />
                            <div>
                                <p class="font-medium">
                                    Per-batch P&L & optional investor portal
                                </p>
                                <p class="text-sm text-emerald-100/80">
                                    See true cycle margins on standalone farms,
                                    or enable investor approvals when funded.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 md:grid-cols-3">
                <article
                    v-for="item in enterprises"
                    :key="item.title"
                    class="rounded-2xl border bg-card p-6 shadow-xs"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="flex size-10 items-center justify-center rounded-xl"
                            :class="item.tone"
                        >
                            <component :is="item.icon" class="size-5" />
                        </span>
                        <span class="text-xs font-medium text-muted-foreground">
                            {{ item.badge }}
                        </span>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">{{ item.title }}</h3>
                    <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                        <li
                            v-for="point in item.points"
                            :key="point"
                            class="flex items-start gap-2"
                        >
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-primary"
                            />
                            <span>{{ point }}</span>
                        </li>
                    </ul>
                </article>
            </section>
        </main>
    </div>
</template>
