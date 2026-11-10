<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ClipboardList, Handshake, Sprout } from 'lucide-vue-next';
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
</script>

<template>
    <div
        class="min-h-screen bg-[#f6faf7] text-[#1c352a] dark:bg-background dark:text-foreground"
    >
        <Head title="Welcome" />
        <header
            class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6 sm:px-8"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-10 items-center justify-center rounded-lg bg-[#214d40] text-white"
                    ><Sprout class="size-5" /></span
                ><span class="text-lg font-semibold tracking-tight"
                    >Farmwell</span
                >
            </div>
            <nav
                aria-label="Account"
                class="flex items-center gap-2 text-sm font-medium"
            >
                <Link
                    v-if="$page.props.auth.user"
                    :href="homeUrl"
                    class="rounded-lg bg-[#214d40] px-4 py-2.5 text-white hover:bg-[#326a54]"
                    >Open workspace</Link
                >
                <template v-else
                    ><Link
                        :href="login()"
                        class="rounded-lg px-4 py-2.5 hover:bg-[#e5f1e9] dark:hover:bg-muted"
                        >Log in</Link
                    ><Link
                        :href="register()"
                        class="rounded-lg bg-[#214d40] px-4 py-2.5 text-white hover:bg-[#326a54]"
                        >Register</Link
                    ></template
                >
            </nav>
        </header>
        <main
            class="mx-auto grid max-w-6xl gap-12 px-6 py-14 sm:px-8 md:py-24 lg:grid-cols-[1.1fr_0.9fr] lg:items-center"
        >
            <div class="max-w-xl">
                <h1
                    class="text-4xl leading-tight font-semibold tracking-tight sm:text-5xl"
                >
                    A better view of your farm, from plan to harvest.
                </h1>
                <p
                    class="mt-5 max-w-lg text-base leading-7 text-[#49675a] dark:text-muted-foreground"
                >
                    Keep farm activity, finances, and investor updates together
                    so your team can make informed decisions.
                </p>
                <Link
                    :href="$page.props.auth.user ? homeUrl : login().url"
                    class="mt-8 inline-flex rounded-lg bg-[#214d40] px-5 py-3 text-sm font-semibold text-white hover:bg-[#326a54]"
                    >{{
                        $page.props.auth.user
                            ? 'Open workspace'
                            : 'Log in to Farmwell'
                    }}</Link
                >
            </div>
            <div class="rounded-2xl bg-[#214d40] p-7 text-white sm:p-9">
                <h2 class="text-xl font-semibold">Everything in one place</h2>
                <div class="mt-6 divide-y divide-white/15">
                    <div class="flex gap-4 py-4">
                        <Sprout class="mt-1 size-5 shrink-0 text-[#bce1cb]" />
                        <div>
                            <p class="font-medium">Farm operations</p>
                            <p class="mt-1 text-sm leading-6 text-[#d6e8de]">
                                Follow your farms, growing cycles, and field
                                activity.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-4 py-4">
                        <ClipboardList
                            class="mt-1 size-5 shrink-0 text-[#bce1cb]"
                        />
                        <div>
                            <p class="font-medium">Financial records</p>
                            <p class="mt-1 text-sm leading-6 text-[#d6e8de]">
                                Track budgets, spending, and harvest proceeds.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-4 py-4">
                        <Handshake
                            class="mt-1 size-5 shrink-0 text-[#bce1cb]"
                        />
                        <div>
                            <p class="font-medium">Investor updates</p>
                            <p class="mt-1 text-sm leading-6 text-[#d6e8de]">
                                Share reviewed progress and keep decisions in
                                context.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
