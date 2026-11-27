import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createInertiaApp } from '@inertiajs/vue3';
import vue from '@vitejs/plugin-vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';

const server = await createServer({
    configFile: false,
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
        },
    },
    server: { middlewareMode: true, hmr: false, ws: false },
    optimizeDeps: { noDiscovery: true, include: [] },
    appType: 'custom',
});
after(() => server.close());
const { default: Portal } = await server.ssrLoadModule(
    '/resources/js/pages/investor-portal/Index.vue',
);
const { default: Terms } = await server.ssrLoadModule(
    '/resources/js/components/InvestorAgreementTerms.vue',
);
const { default: Decisions } = await server.ssrLoadModule(
    '/resources/js/components/InvestorPortalDecisions.vue',
);
const { default: Updates } = await server.ssrLoadModule(
    '/resources/js/components/InvestorPortalUpdates.vue',
);
const { default: CycleHealth } = await server.ssrLoadModule(
    '/resources/js/components/InvestorCycleHealthCard.vue',
);
const { default: Portfolio } = await server.ssrLoadModule(
    '/resources/js/pages/investor-portal/Portfolio.vue',
);
const { usePhotoZoom } = await server.ssrLoadModule(
    '/resources/js/composables/usePhotoZoom.ts',
);

function agreement(overrides = {}) {
    return {
        id: 1,
        title: 'Rice and pepper agreement',
        investorName: 'Test investor',
        farmName: 'Crop farm',
        currency: 'NGN',
        statusLabel: 'Active',
        amountCommittedMinor: 114450000,
        amountCommitted: '1144500.00',
        amountFundedMinor: 0,
        amountFunded: '0.00',
        investorProfitSharePercentage: 30,
        farmProfitSharePercentage: 70,
        capitalRecoveryRuleLabel: 'Capital first',
        productionCycleName: null,
        fundingModel: 'Phased funding across separate crops and seasons.',
        roleResponsibilities:
            'The farmer manages operations; the investor reviews funding.',
        publicNotes:
            'Rice, a separate season: NGN 606,500.\nDry Season Pepper: NGN 538,000.',
        internalNotes: 'PRIVATE BANKING INSTRUCTIONS',
        startsOn: null,
        endsOn: null,
        documents: [],
        summary: {
            released: '0.00',
            spent: '0.00',
            balance: '0.00',
            saleNet: '0.00',
            capitalRecovered: '0.00',
            investorShare: '0.00',
        },
        fundingPhases: [],
        expenses: [],
        harvestRecords: [],
        saleRecords: [],
        distributionRecords: [],
        externalTransfers: [],
        approvalRequests: [],
        aiSummaries: [],
        activities: [],
        tasks: [],
        planChanges: [],
        comments: [],
        ...overrides,
    };
}

async function renderPortal(item) {
    const result = await createInertiaApp({
        page: {
            component: 'investor-portal/Index',
            url: '/test-farm/investor-portal',
            version: 'test',
            props: {
                agreements: [item],
                currentTeam: { id: 1, name: item.farmName, slug: 'test-farm' },
                errors: {},
            },
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => Portal,
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) }).use(plugin),
        render: renderToString,
    });

    return result.body.slice(result.body.indexOf('<div data-server-rendered='));
}

test('portal renders combined commitment, separate crop allocations and profit shares without exposing internal notes', async () => {
    const html = await renderPortal(agreement());

    for (const text of [
        'Committed capital',
        '₦1,144,500.00 NGN',
        'Recorded funding',
        '₦0.00 NGN',
        '30%',
        '70%',
        'NGN 606,500',
        'NGN 538,000',
        'separate season',
        'Capital first',
        'Phased funding',
    ]) {
        assert.ok(
            html.includes(text),
            `Missing visible agreement term: ${text}`,
        );
    }

    assert.doesNotMatch(html, /PRIVATE BANKING INSTRUCTIONS/);
    assert.ok(html.indexOf('Your agreement') < html.indexOf('Released'));
});

test('the overview shows funded progress and calls out requests that need a decision', async () => {
    const html = await renderPortal(
        agreement({
            amountFundedMinor: 57225000,
            amountFunded: '572250.00',
            approvalRequests: [{ status: 'pending' }, { status: 'approved' }],
        }),
    );

    assert.match(html, /<progress[^>]+value="50"/);
    assert.match(html, /1 request needs your decision/);
    assert.match(html, /Review requests/);
});

test('the decision queue puts pending requests first and only shows a decision form for pending requests', async () => {
    const request = {
        id: 7,
        requestType: 'funding_release',
        requestTypeLabel: 'Funding release',
        status: 'pending',
        statusLabel: 'Pending',
        subjectLabel: 'Stocking milestone',
        requestedAmount: '75000.00',
        approvedAmount: null,
        currency: 'NGN',
        requestedAt: '2026-11-14T12:00:00Z',
        milestone: 'Pond ready',
        milestoneEvidence: [
            {
                id: 3,
                fileName: 'pond.jpg',
                downloadUrl: '/test-farm/investor-evidence/3',
            },
        ],
        requesterComment: 'Tank preparation is complete.',
    };
    const html = await renderToString(
        createSSRApp({
            render: () =>
                h(Decisions, {
                    requests: [
                        {
                            ...request,
                            id: 6,
                            status: 'approved',
                            statusLabel: 'Approved',
                            subjectLabel: 'Earlier decision',
                            approvedAmount: '65000.00',
                        },
                        request,
                    ],
                    currentTeamSlug: 'test-farm',
                    agreementId: 1,
                }),
        }),
    );

    assert.ok(
        html.indexOf('Stocking milestone') < html.indexOf('Earlier decision'),
    );
    assert.match(html, /Choose a decision/);
    assert.match(html, /Tank preparation is complete/);
    assert.match(html, /href="\/test-farm\/investor-evidence\/3"/);
    assert.equal((html.match(/<form\b/g) ?? []).length, 1);
});

test('farm updates show photo viewer buttons and document downloads in the activity trail', async () => {
    const html = await renderToString(
        createSSRApp({
            render: () =>
                h(Updates, {
                    agreement: agreement({
                        activities: [
                            {
                                id: 1,
                                activityType: 'Shared field photos',
                                activityDate: '2026-11-15',
                                summary: null,
                                evidence: [
                                    {
                                        id: 3,
                                        fileName: 'pond.jpg',
                                        downloadUrl:
                                            '/test-farm/investor-evidence/3',
                                        previewUrl:
                                            '/test-farm/investor-evidence/3?preview=1',
                                        caption: 'Shared pond photo',
                                    },
                                    {
                                        id: 4,
                                        fileName: 'inspection.pdf',
                                        downloadUrl:
                                            '/test-farm/investor-evidence/4',
                                        previewUrl: null,
                                        caption: null,
                                    },
                                ],
                            },
                        ],
                        tasks: [
                            {
                                id: 1,
                                title: 'Clean the equipment',
                                dueOn: null,
                                status: 'in_progress',
                            },
                        ],
                        aiSummaries: [
                            {
                                id: 1,
                                html: '<p>Weekly update</p>',
                                publishedAt: '2026-11-14T12:00:00Z',
                            },
                        ],
                    }),
                }),
        }),
    );

    assert.match(html, /datetime="2026-11-15"[^>]*>15 Nov 2026/);
    assert.match(html, /href="\/test-farm\/investor-evidence\/4"/);
    assert.doesNotMatch(html, /href="\/test-farm\/investor-evidence\/3"/);
    assert.match(
        html,
        /<button[^>]+aria-haspopup="dialog"[^>]+aria-label="View photo: Shared pond photo \(pond.jpg\)"/,
    );
    assert.match(
        html,
        /<img[^>]+src="\/test-farm\/investor-evidence\/3\?preview=1"[^>]+alt=""/,
    );
    assert.ok(
        html.indexOf('Shared field photos') < html.indexOf('Weekly update'),
    );
    assert.ok(
        html.indexOf('Weekly update') < html.indexOf('Clean the equipment'),
    );
});

test('photo zoom keeps the fitted image within reach and restores the swipable view at normal size', () => {
    const zoom = usePhotoZoom(() => ({
        clientWidth: 390,
        clientHeight: 600,
        naturalWidth: 3000,
        naturalHeight: 4000,
    }));

    zoom.zoomBy(2);
    zoom.panBy(600, -600);

    assert.equal(zoom.zoomed.value, true);
    assert.equal(zoom.transform.value, 'translate(195px, -220px) scale(2)');

    zoom.zoomBy(0.5);

    assert.equal(zoom.zoomed.value, false);
    assert.equal(zoom.transform.value, undefined);
});

test('fish commitment stays distinct from the full cycle estimate and shows its own terms', async () => {
    const html = await renderPortal(
        agreement({
            title: 'Fish-farm agreement',
            farmName: 'Fish farm',
            amountCommitted: '248500.00',
            investorProfitSharePercentage: 35,
            farmProfitSharePercentage: 65,
            startsOn: '2026-11-01',
            publicNotes:
                'Initial 1,500-fish batch. Estimated cycle budget: NGN 887,800. Capital retained for the next agreed cycle before profit distribution.',
            fundingModel:
                'First-month pledge; later instalments agreed in writing.',
        }),
    );

    for (const text of [
        '₦248,500.00 NGN',
        '35%',
        '65%',
        '1,500-fish',
        'NGN 887,800',
        '2026-11-01',
        'End not recorded',
        'First-month pledge',
        'Capital retained',
    ]) {
        assert.ok(html.includes(text), `Missing fish agreement term: ${text}`);
    }

    assert.doesNotMatch(
        html,
        /₦1,144,500|NGN 606,500|NGN 538,000|PRIVATE BANKING INSTRUCTIONS/,
    );
});

test('missing scope is explicit, zero shares remain visible and notes are escaped', async () => {
    const render = (item) =>
        renderToString(
            createSSRApp({ render: () => h(Terms, { agreement: item }) }),
        );
    const empty = await render(
        agreement({
            publicNotes: null,
            fundingModel: null,
            roleResponsibilities: null,
            investorProfitSharePercentage: 0,
            farmProfitSharePercentage: 100,
        }),
    );
    assert.match(empty, /No crop or season breakdown has been recorded/);
    assert.match(empty, />0%\s*</);
    assert.match(empty, />100%\s*</);
    assert.doesNotMatch(
        empty,
        /Agreement period|Production cycle|PRIVATE BANKING INSTRUCTIONS/,
    );
    const escaped = await render(
        agreement({
            publicNotes: '<script>alert(1)</script>',
            productionCycleName: 'Dry season 2027',
        }),
    );
    assert.ok(escaped.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));
    assert.doesNotMatch(escaped, /<script>alert/);
    assert.ok(escaped.includes('Dry season 2027'));
});

test('clarification requested decisions allow approve or reject without request clarification', async () => {
    const html = await renderToString(
        createSSRApp({
            render: () =>
                h(Decisions, {
                    requests: [
                        {
                            id: 11,
                            investorAgreementId: 1,
                            agreementTitle: 'Fish agreement',
                            investorName: 'Test investor',
                            requestType: 'expense',
                            requestTypeLabel: 'Expense',
                            triggerType: 'threshold',
                            triggerTypeLabel: 'Threshold',
                            status: 'clarification_requested',
                            statusLabel: 'Clarification requested',
                            subjectLabel: 'Pump replacement',
                            requestedAmount: '45000.00',
                            approvedAmount: null,
                            currency: 'NGN',
                            expenseDescription: 'Replace borehole pump.',
                            milestone: null,
                            milestoneEvidence: [],
                            requesterComment: 'Pump failed.',
                            decisionComment: 'Please attach vendor quote.',
                            requestedAt: '2026-11-15T10:00:00Z',
                            decidedAt: null,
                        },
                    ],
                    currentTeamSlug: 'test-farm',
                    agreementId: 1,
                }),
        }),
    );

    assert.equal((html.match(/<form\b/g) ?? []).length, 1);
    assert.match(html, /Approve/);
    assert.match(html, /Reject/);
    assert.doesNotMatch(html, /<button[^>]*>[^<]*Request clarification/);
});

test('cycle health snapshot card renders domain telemetry cleanly without private fields', async () => {
    const html = await renderToString(
        createSSRApp({
            render: () =>
                h(CycleHealth, {
                    snapshot: {
                        cycleId: 5,
                        cycleName: 'Catfish Batch 3',
                        season: 'Dry season',
                        domain: 'aquaculture',
                        domainLabel: 'Fish / Aquaculture',
                        farmType: 'aquaculture',
                        farmTypeLabel: 'Aquaculture',
                        status: 'active',
                        statusLabel: 'Active',
                        plannedStartOn: '2026-11-01',
                        plannedEndOn: '2027-03-01',
                        ageDays: 25,
                        progressPercent: 40,
                        logCount: 12,
                        lastLoggedOn: '2026-11-25',
                        stock: {
                            initialCount: 1500,
                            currentCount: 1470,
                            survivalRatePercent: 98,
                            mortalityRatePercent: 2,
                        },
                        feeding: {
                            totalFeedKg: 210,
                            averageDailyFeedKg: 17.5,
                            latestAverageWeightG: 185,
                            estimatedBiomassKg: 271.95,
                            fcr: 1.25,
                        },
                        poultry: {
                            totalEggs: 0,
                            totalCrates: 0,
                            recentLayRatePercent: null,
                        },
                        aquaculture: {
                            waterStatus: 'optimal',
                            waterExchangeCount: 3,
                            latestPh: 7.1,
                            latestTempC: 27.5,
                            latestDissolvedOxygen: 5.4,
                            latestAmmonia: 0.12,
                        },
                        crop: {
                            currentGrowthStage: null,
                            currentGrowthStageLabel: null,
                            stageProgressPercent: 40,
                            totalRainfallMm: 0,
                            irrigationCount: 0,
                            latestPestPressure: null,
                            latestPestPressureLabel: null,
                        },
                        series: {
                            dates: ['Nov 24', 'Nov 25'],
                            feedKg: [16, 18],
                            eggs: [0, 0],
                            rainfallMm: [0, 0],
                        },
                    },
                }),
        }),
    );

    for (const expected of [
        'Catfish Batch 3',
        'Optimal water',
        '98% survival',
        '271.95 kg',
        'FCR 1.25',
        '5.4 mg/L',
    ]) {
        assert.ok(
            html.includes(expected),
            `Missing snapshot value: ${expected}`,
        );
    }
});

test('portfolio renders consolidated summary totals and farm type badges without exposing internal notes', async () => {
    const result = await createInertiaApp({
        page: {
            component: 'investor-portal/Portfolio',
            url: '/my-investments',
            version: 'test',
            props: {
                farms: [
                    {
                        id: 2,
                        name: 'Sanni Fish Farm',
                        farmType: 'aquaculture',
                        farmTypeLabel: 'Aquaculture',
                        teamSlug: 'sanni-fish',
                        agreements: [
                            {
                                id: 9,
                                title: 'First catfish batch',
                                status: 'active',
                                statusLabel: 'Active',
                                currency: 'NGN',
                                committed: '248500.00',
                                funded: '124250.00',
                                fundingPercent: 50,
                                capitalRecovered: '50000.00',
                                investorShare: '20000.00',
                                productionCycleName: 'Batch 1',
                                cycleProgressPercent: 60,
                                pendingDecisionCount: 1,
                                latestActivity: null,
                            },
                        ],
                    },
                ],
                attention: [],
                summary: {
                    farmCount: 1,
                    agreementCount: 1,
                    activeAgreementCount: 1,
                    pendingDecisionCount: 1,
                    totalsByCurrency: [
                        {
                            currency: 'NGN',
                            committed: '248500.00',
                            funded: '124250.00',
                            fundingPercent: 50,
                            released: '100000.00',
                            spent: '75000.00',
                            capitalRecovered: '50000.00',
                            investorShare: '20000.00',
                            totalReturned: '70000.00',
                        },
                    ],
                },
                errors: {},
            },
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => Portfolio,
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) }).use(plugin),
        render: renderToString,
    });

    const html = result.body.slice(
        result.body.indexOf('<div data-server-rendered='),
    );
    assert.ok(html.includes('Sanni Fish Farm'));
    assert.ok(html.includes('Aquaculture'));
    assert.ok(html.includes('50% of commitment'));
    assert.ok(html.includes('₦70,000.00 NGN total returned'));
});

test('updates prefer thumbnailUrl on grid images while preserving previewUrl for lightbox and render distinct sidebar labels', async () => {
    const html = await renderToString(
        createSSRApp({
            render: () =>
                h(Updates, {
                    agreement: agreement({
                        activities: [
                            {
                                id: 1,
                                activityType: 'Shared field photos',
                                activityDate: '2026-11-22',
                                summary: null,
                                evidence: [
                                    {
                                        id: 7,
                                        fileName: 'IMG_20261122.jpg',
                                        downloadUrl:
                                            '/farm/investor-evidence/7',
                                        thumbnailUrl:
                                            '/farm/investor-evidence/7?thumb=1',
                                        previewUrl:
                                            '/farm/investor-evidence/7?preview=1',
                                        caption: null,
                                    },
                                ],
                            },
                        ],
                    }),
                }),
        }),
    );

    assert.match(html, /src="\/farm\/investor-evidence\/7\?thumb=1"/);

    const { default: AppSidebar } = await server.ssrLoadModule(
        '/resources/js/components/AppSidebar.vue',
    );
    const { default: SidebarProvider } = await server.ssrLoadModule(
        '/resources/js/components/ui/sidebar/SidebarProvider.vue',
    );
    const sidebarPage = await createInertiaApp({
        page: {
            component: 'SidebarLabelTest',
            url: '/my-investments',
            version: 'test',
            props: {
                auth: {
                    user: {
                        id: 1,
                        name: 'Super Investor',
                        email: 'super@example.test',
                    },
                },
                canManagePlatform: true,
                currentTeam: {
                    id: 1,
                    name: 'Fish Farm',
                    slug: 'fish-farm',
                    role: 'investor',
                },
                currentTeamPermissions: {
                    canViewInvestorPortal: true,
                    canViewFarmOperations: false,
                    canViewFinance: false,
                    canViewInvestorAgreements: false,
                },
                teams: [],
                errors: {},
            },
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => ({
            render: () =>
                h(SidebarProvider, null, { default: () => h(AppSidebar) }),
        }),
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) }).use(plugin),
        render: renderToString,
    });

    assert.match(sidebarPage.body, />Investments</);
    assert.match(sidebarPage.body, />Administration</);
    assert.doesNotMatch(sidebarPage.body, />Platform</);
});

test('EvidenceGallery renders compact photo buttons and paperclip document fallback', async () => {
    const { default: EvidenceGallery } = await server.ssrLoadModule(
        '/resources/js/components/farm/EvidenceGallery.vue',
    );
    const html = await renderToString(
        createSSRApp({
            render: () =>
                h(EvidenceGallery, {
                    compact: true,
                    dialogTitle: 'Field diary photos',
                    items: [
                        {
                            id: 1,
                            fileName: 'pond.jpg',
                            caption: 'Pond check',
                            downloadUrl: '/team/evidence/1',
                            thumbnailUrl: '/team/evidence/1?thumb=1',
                            previewUrl: '/team/evidence/1?preview=1',
                        },
                        {
                            id: 2,
                            fileName: 'invoice.pdf',
                            caption: null,
                            downloadUrl: '/team/evidence/2',
                            thumbnailUrl: null,
                            previewUrl: null,
                        },
                    ],
                }),
        }),
    );

    assert.match(html, /src="\/team\/evidence\/1\?thumb=1"/);
    assert.match(html, /w-28/);
    assert.match(html, /Pond check/);
    assert.match(html, /href="\/team\/evidence\/2"/);
    assert.match(html, /invoice\.pdf/);
});

test('portal renders workspace empty state, equal commitment subtitle, and formatted question record dates', async () => {
    const portalWithAgreement = await renderPortal(agreement());
    assert.ok(
        portalWithAgreement.includes(
            'Total capital commitment agreed for this investment.',
        ),
    );

    const emptyPortalResult = await createInertiaApp({
        page: {
            component: 'investor-portal/Index',
            url: '/muhammed-sannis-fish-farm/investor-portal',
            version: 'test',
            props: {
                agreements: [],
                currentTeam: {
                    id: 9,
                    name: "Muhammed Sanni's Fish Farm",
                    slug: 'muhammed-sannis-fish-farm',
                },
                errors: {},
            },
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => Portal,
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) }).use(plugin),
        render: renderToString,
    });
    const emptyHtml = emptyPortalResult.body.slice(
        emptyPortalResult.body.indexOf('<div data-server-rendered='),
    );
    assert.match(emptyHtml, /Awaiting agreement setup in Muhammed Sanni/);
    assert.ok(emptyHtml.includes('Back to all investments'));
    assert.match(emptyHtml, /href="\/my-investments"/);

    const { default: Questions } = await server.ssrLoadModule(
        '/resources/js/components/InvestorPortalQuestions.vue',
    );
    const questionsHtml = await renderToString(
        createSSRApp({
            render: () =>
                h(Questions, {
                    currentTeamSlug: 'test-farm',
                    agreement: agreement({
                        fundingTimeline: [],
                        expenses: [
                            {
                                id: 1,
                                categoryName: 'Fish feed',
                                incurredOn: '2026-11-22',
                            },
                        ],
                        harvestRecords: [
                            {
                                id: 2,
                                commodityName: 'Catfish',
                                harvestedOn: '2026-11-24',
                            },
                        ],
                        saleRecords: [
                            {
                                id: 3,
                                buyerName: 'Kano Market',
                                soldOn: '2026-11-25',
                            },
                        ],
                        activities: [
                            {
                                id: 4,
                                activityType: 'Feeding',
                                activityDate: '2026-11-26',
                            },
                        ],
                    }),
                }),
        }),
    );

    assert.ok(questionsHtml.includes('Expense: Fish feed (22 Nov 2026)'));
    assert.ok(questionsHtml.includes('Harvest: Catfish (24 Nov 2026)'));
    assert.ok(questionsHtml.includes('Sale: Kano Market (25 Nov 2026)'));
    assert.ok(questionsHtml.includes('Update: Feeding (26 Nov 2026)'));
    assert.doesNotMatch(
        questionsHtml,
        /\((2026-11-22|2026-11-24|2026-11-25|2026-11-26)\)/,
    );
});

test('phone login shows Create an account link on full-workspace hosts only when not invited', async () => {
    const { default: PhoneLogin } = await server.ssrLoadModule(
        '/resources/js/pages/auth/PhoneLogin.vue',
    );
    const renderPhoneLogin = async (overrides = {}) => {
        const result = await createInertiaApp({
            page: {
                component: 'auth/PhoneLogin',
                url: '/login/phone',
                version: 'test',
                props: {
                    invite: null,
                    teamName: null,
                    awaitingCode: false,
                    codeLifetimeMinutes: 5,
                    callingCode: '+234',
                    pilot: true,
                    emailSignInAllowed: true,
                    countries: { NG: '+234', QA: '+974' },
                    errors: {},
                    ...overrides,
                },
                clearHistory: false,
                encryptHistory: false,
            },
            resolve: () => PhoneLogin,
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) }).use(plugin),
            render: renderToString,
        });

        return result.body.slice(
            result.body.indexOf('<div data-server-rendered='),
        );
    };

    const standardHtml = await renderPhoneLogin({
        emailSignInAllowed: true,
        invite: null,
    });
    assert.ok(standardHtml.includes('Sign in with email'));
    assert.ok(standardHtml.includes('Create an account'));
    assert.match(standardHtml, /href="\/register"/);

    const invitedHtml = await renderPhoneLogin({
        emailSignInAllowed: true,
        invite: 'abc123',
        teamName: 'Sanni Farm',
    });
    assert.ok(invitedHtml.includes('Sign in with email'));
    assert.doesNotMatch(invitedHtml, /Create an account/);
    assert.doesNotMatch(invitedHtml, /href="\/register"/);

    const pilotOnlyHtml = await renderPhoneLogin({
        emailSignInAllowed: false,
        invite: null,
    });
    assert.doesNotMatch(pilotOnlyHtml, /Sign in with email/);
    assert.doesNotMatch(pilotOnlyHtml, /Create an account/);
});

test('AppSidebar hides AI Summaries and Onboard Farm when workspace plan restricts them', async () => {
    const { default: AppSidebar } = await server.ssrLoadModule(
        '/resources/js/components/AppSidebar.vue',
    );
    const { default: SidebarProvider } = await server.ssrLoadModule(
        '/resources/js/components/ui/sidebar/SidebarProvider.vue',
    );

    const starterSidebar = await createInertiaApp({
        page: {
            component: 'SidebarPlanTest',
            url: '/starter-farm/dashboard',
            version: 'test',
            props: {
                auth: {
                    user: {
                        id: 1,
                        name: 'Starter Farmer',
                        email: 'starter@example.test',
                    },
                },
                canManagePlatform: false,
                currentTeam: {
                    id: 1,
                    name: 'Starter Farm',
                    slug: 'starter-farm',
                    role: 'owner',
                },
                currentTeamPermissions: {
                    canViewFarmOperations: true,
                    canManageFarmOperations: true,
                    canManageFieldWork: true,
                    canViewFinance: true,
                    canManageInvestorAgreements: false,
                },
                currentTeamFeatures: {
                    investorMode: false,
                    needsOnboarding: false,
                    canAddFarm: false,
                    plan: {
                        key: 'starter',
                        label: 'Starter (Free)',
                        description: 'Starter plan',
                        maxFarms: 1,
                        maxMembers: 2,
                        allowsPhoneInvitations: false,
                        allowsAiSummaries: false,
                        allowsInvestorMode: false,
                        allowsAssistant: false,
                    },
                },
                teams: [],
                errors: {},
            },
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => ({
            render: () =>
                h(SidebarProvider, null, { default: () => h(AppSidebar) }),
        }),
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) }).use(plugin),
        render: renderToString,
    });

    assert.match(starterSidebar.body, />Field Diary</);
    assert.doesNotMatch(starterSidebar.body, />Onboard Farm</);
    assert.doesNotMatch(starterSidebar.body, />AI Summaries</);
    assert.doesNotMatch(starterSidebar.body, />AI Usage</);

    const adminSidebar = await createInertiaApp({
        page: {
            component: 'SidebarAdminTest',
            url: '/platform/ai-usage',
            version: 'test',
            props: {
                auth: {
                    user: {
                        id: 2,
                        name: 'Platform Admin',
                        email: 'admin@example.test',
                    },
                },
                canManagePlatform: true,
                currentTeam: null,
                currentTeamPermissions: null,
                currentTeamFeatures: null,
                teams: [],
                errors: {},
            },
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => ({
            render: () =>
                h(SidebarProvider, null, { default: () => h(AppSidebar) }),
        }),
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) }).use(plugin),
        render: renderToString,
    });

    assert.match(adminSidebar.body, />Platform admin</);
    assert.match(adminSidebar.body, />AI Usage</);
    assert.match(adminSidebar.body, /href="\/platform\/ai-usage"/);
});

test('FarmwellAssistantDrawer renders trigger only on enabled plans without platform access', async () => {
    const { default: FarmwellAssistantDrawer } = await server.ssrLoadModule(
        '/resources/js/components/assistant/FarmwellAssistantDrawer.vue',
    );
    const { default: AssistantApprovalCard } = await server.ssrLoadModule(
        '/resources/js/components/assistant/AssistantApprovalCard.vue',
    );

    const renderDrawer = async ({
        allowsAssistant,
        platformAccess = null,
        platformImpersonation = null,
    }) =>
        createInertiaApp({
            page: {
                component: 'AssistantHost',
                url: '/pro-farm/dashboard',
                props: {
                    auth: {
                        user: {
                            id: 1,
                            name: 'Farm Owner',
                            email: 'owner@example.test',
                        },
                    },
                    canManagePlatform: false,
                    platformAccess,
                    platformImpersonation,
                    currentTeam: {
                        id: 2,
                        name: 'Pro Farm',
                        slug: 'pro-farm',
                        role: 'owner',
                    },
                    currentTeamFeatures: {
                        investorMode: false,
                        needsOnboarding: false,
                        canAddFarm: true,
                        plan: {
                            key: allowsAssistant ? 'pro' : 'starter',
                            label: allowsAssistant ? 'Pro Farm' : 'Starter',
                            description: 'Workspace plan',
                            maxFarms: allowsAssistant ? null : 1,
                            maxMembers: allowsAssistant ? null : 2,
                            allowsPhoneInvitations: allowsAssistant,
                            allowsAiSummaries: allowsAssistant,
                            allowsInvestorMode: false,
                            allowsAssistant,
                        },
                    },
                    teams: [],
                    errors: {},
                },
                clearHistory: false,
                encryptHistory: false,
            },
            resolve: () => ({
                render: () => h(FarmwellAssistantDrawer),
            }),
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) }).use(plugin),
            render: renderToString,
        });

    const proPage = await renderDrawer({ allowsAssistant: true });
    assert.match(proPage.body, /Ask Farmwell/);

    const starterPage = await renderDrawer({ allowsAssistant: false });
    assert.doesNotMatch(starterPage.body, /Ask Farmwell/);

    const adminReadOnlyPage = await renderDrawer({
        allowsAssistant: true,
        platformAccess: {
            team_name: 'Pro Farm',
            reason: 'Support audit',
            expires_at: 2_000_000_000,
        },
    });
    assert.doesNotMatch(adminReadOnlyPage.body, /Ask Farmwell/);

    const impersonatedPage = await renderDrawer({
        allowsAssistant: true,
        platformImpersonation: {
            id: 'imp-1',
            impersonator_id: 99,
            impersonator_name: 'Admin User',
            target_user_id: 1,
            target_user_name: 'Farm Owner',
            target_user_email: 'owner@example.test',
            reason: 'Verify owner assistant',
            started_at: 1_999_999_000,
            expires_at: 2_000_000_000,
        },
    });
    assert.match(impersonatedPage.body, /Ask Farmwell/);

    const cardHtml = await renderToString(
        createSSRApp({
            render: () =>
                h(AssistantApprovalCard, {
                    approval: {
                        id: 'call_1',
                        tool: 'RecordExpenseTool',
                        reason: 'Record expense of 5000 on 2026-11-27: Layer feed',
                        arguments: {
                            farm_id: 12,
                            amount: '5000',
                            description: 'Layer feed',
                        },
                    },
                }),
        }),
    );

    assert.match(cardHtml, /Record Expense/);
    assert.match(cardHtml, /Approval required/);
    assert.match(cardHtml, /Layer feed/);
    assert.match(cardHtml, /Approve/);
    assert.match(cardHtml, /Decline/);
    assert.match(cardHtml, /Edit/);
});

test('AssistantMessageContent renders formatted markdown tables safely without XSS', async () => {
    const { parseAssistantMarkdown } = await server.ssrLoadModule(
        '/resources/js/lib/assistantMarkdown.ts',
    );
    const { default: AssistantMessageContent } = await server.ssrLoadModule(
        '/resources/js/components/assistant/AssistantMessageContent.vue',
    );

    const sampleMarkdown = [
        '### Investment summary',
        '**MDSxclusive Farms — Rice and Dry Season Pepper**',
        '',
        '| Item | Amount |',
        '|---|---:|',
        '| Committed capital | ₦1,144,500.00 |',
        '| **Current balance** | **₦0.00** |',
        '',
        '- Status is `active`',
        '> Verify bank alert before confirming release.',
        '',
        'Unsafe <script>alert(1)<\/script> [bad](javascript:alert(1)) [Portal](/mdsxclusive-farms/investor-portal)',
    ].join('\n');

    const blocks = parseAssistantMarkdown(sampleMarkdown);
    assert.equal(blocks[0].type, 'heading');
    assert.equal(blocks[2].type, 'table');
    assert.equal(blocks[2].alignments[1], 'right');
    assert.equal(blocks[2].rows[1].highlighted, true);

    const rendered = await renderToString(
        createSSRApp({
            render: () =>
                h(AssistantMessageContent, { content: sampleMarkdown }),
        }),
    );

    assert.match(rendered, /<h3[^>]*>(?:<!--.*?-->)*Investment summary/);
    assert.match(rendered, /role="region"/);
    assert.match(rendered, /tabindex="0"/);
    assert.match(rendered, /<table/);
    assert.match(rendered, /scope="col"/);
    assert.match(rendered, /scope="row"/);
    assert.match(rendered, /tabular-nums/);
    assert.match(rendered, /bg-primary\/\[0\.08\]/);
    assert.match(rendered, /<code[^>]*>active<\/code>/);
    assert.match(rendered, /<blockquote/);
    assert.match(rendered, /href="\/mdsxclusive-farms\/investor-portal"/);
    assert.doesNotMatch(rendered, /<script>/);
    assert.doesNotMatch(rendered, /javascript:/);
    assert.doesNotMatch(rendered, /bad\)/);

    // Partial mid-stream table row parses cleanly without throwing
    const partialBlocks = parseAssistantMarkdown(
        '| Item | Amount |\n|---|---:|\n| Pending',
    );
    assert.equal(partialBlocks[0].type, 'table');
    assert.equal(partialBlocks[0].rows[0].cells[1], '');
});

test('AssistantInputCard renders rich controls, accessible fieldsets, optional section, and submitted state', async () => {
    const { default: AssistantInputCard } = await server.ssrLoadModule(
        '/resources/js/components/assistant/AssistantInputCard.vue',
    );

    const sampleForm = {
        title: 'Funding Release Request',
        description: 'Matched MDSxclusive Farms — Rice and Dry Season Pepper',
        submit_label: 'Prepare funding request',
        currency: 'NGN',
        fields: [
            {
                name: 'phase_name',
                label: 'Phase name',
                type: 'text',
                required: true,
                placeholder: 'e.g. Land Prep & Seeds',
            },
            {
                name: 'requested_amount',
                label: 'Requested amount',
                type: 'currency',
                required: true,
            },
            {
                name: 'decision_mode',
                label: 'Priority',
                type: 'radio',
                required: true,
                options: [
                    {
                        value: 'urgent',
                        label: 'Urgent',
                        hint: 'Needed this week',
                    },
                    { value: 'scheduled', label: 'Scheduled' },
                ],
            },
            {
                name: 'tags',
                label: 'Activities covered',
                type: 'multiselect',
                required: false,
                options: [
                    { value: 'clearing', label: 'Land clearing' },
                    { value: 'seedlings', label: 'Seedlings' },
                ],
            },
            {
                name: 'production_cycle_id',
                label: 'Production cycle',
                type: 'select',
                required: false,
                options: [{ value: '12', label: 'Dry Season Pepper 2026' }],
            },
            {
                name: 'expected_on',
                label: 'Target date',
                type: 'date',
                required: false,
            },
        ],
    };

    const activeHtml = await renderToString(
        createSSRApp({
            render: () =>
                h(AssistantInputCard, {
                    formId: 'msg-1',
                    form: sampleForm,
                    submitted: false,
                }),
        }),
    );

    assert.match(activeHtml, /Funding Release Request/);
    assert.match(activeHtml, /Input needed/);
    assert.match(activeHtml, /<form[^>]*novalidate/);
    assert.match(activeHtml, /<fieldset/);
    assert.match(activeHtml, /<legend[^>]*>Priority/);
    assert.match(activeHtml, /inputmode="decimal"/);
    assert.match(activeHtml, /₦/);
    assert.match(activeHtml, /aria-expanded="false"/);
    assert.match(activeHtml, /Optional details \(3\)/);
    assert.match(activeHtml, /<select/);
    assert.match(activeHtml, /type="date"/);
    assert.match(activeHtml, /Prepare funding request/);

    const submittedHtml = await renderToString(
        createSSRApp({
            render: () =>
                h(AssistantInputCard, {
                    formId: 'msg-1',
                    form: sampleForm,
                    submitted: true,
                }),
        }),
    );

    assert.match(submittedHtml, /Funding Release Request/);
    assert.match(submittedHtml, /Submitted/);
    assert.doesNotMatch(submittedHtml, /<form/);
});

test('AI usage formatters, daily chart, and Platform AiUsage page render cleanly with populated and empty states', async () => {
    const {
        formatUsdFromMicrodollars,
        formatTokenCount,
        formatDurationMs,
        formatRatePerMillion,
    } = await server.ssrLoadModule('/resources/js/lib/aiUsageFormat.ts');
    const { default: AiDailyUsageChart } = await server.ssrLoadModule(
        '/resources/js/components/platform/AiDailyUsageChart.vue',
    );
    const { default: AiUsagePage } = await server.ssrLoadModule(
        '/resources/js/pages/platform/AiUsage.vue',
    );

    assert.equal(formatUsdFromMicrodollars(0), '$0.00');
    assert.equal(formatUsdFromMicrodollars(450), '$0.0005');
    assert.equal(formatUsdFromMicrodollars(124_500), '$0.1245');
    assert.equal(formatUsdFromMicrodollars(14_820_000), '$14.82');
    assert.equal(
        formatUsdFromMicrodollars(1_250_000_000, { compact: true }),
        '$1.3k',
    );
    assert.equal(formatTokenCount(1_250_000), '1.25M');
    assert.equal(formatTokenCount(48_250), '48.3K');
    assert.equal(formatTokenCount(850), '850');
    assert.equal(formatDurationMs(null), '—');
    assert.equal(formatDurationMs(420), '420ms');
    assert.equal(formatDurationMs(2450), '2.45s');
    assert.equal(formatRatePerMillion(null), '—');
    assert.equal(formatRatePerMillion(0.1), '$0.100/1M');
    assert.equal(formatRatePerMillion(10), '$10.00/1M');

    const emptyChartHtml = await renderToString(
        createSSRApp({
            render: () => h(AiDailyUsageChart, { days: [] }),
        }),
    );
    assert.match(emptyChartHtml, /No AI calls recorded in this date window/);

    const sampleDays = [
        {
            date: '2026-11-26',
            label: '26 Nov',
            costMicrodollars: 15_000,
            inputCostMicrodollars: 5_000,
            outputCostMicrodollars: 10_000,
            cacheCostMicrodollars: 0,
            calls: 2,
            failedCalls: 0,
            inputTokens: 2500,
            outputTokens: 1000,
            cacheReadTokens: 0,
            totalTokens: 3500,
        },
        {
            date: '2026-11-27',
            label: '27 Nov',
            costMicrodollars: 41_000,
            inputCostMicrodollars: 20_000,
            outputCostMicrodollars: 20_000,
            cacheCostMicrodollars: 1_000,
            calls: 3,
            failedCalls: 1,
            inputTokens: 20_000,
            outputTokens: 2_000,
            cacheReadTokens: 10_000,
            totalTokens: 22_000,
        },
    ];

    const populatedPage = await createInertiaApp({
        page: {
            component: 'platform/AiUsage',
            url: '/platform/ai-usage',
            props: {
                filters: {
                    range: '30d',
                    team_id: null,
                    user_id: null,
                    feature: null,
                    model: null,
                    status: null,
                },
                summary: {
                    totalCostMicrodollars: 56_000,
                    previousCostMicrodollars: 28_000,
                    costDeltaPercent: 100,
                    inputCostMicrodollars: 25_000,
                    outputCostMicrodollars: 30_000,
                    cacheCostMicrodollars: 1_000,
                    estimatedCacheSavingsMicrodollars: 19_000,
                    totalCalls: 5,
                    completedCalls: 4,
                    pausedCalls: 0,
                    failedCalls: 1,
                    successRatePercent: 80,
                    totalInputTokens: 22_500,
                    uncachedInputTokens: 12_500,
                    cacheReadTokens: 10_000,
                    cacheWriteTokens: 0,
                    totalOutputTokens: 3_000,
                    reasoningTokens: 0,
                    totalTokens: 25_500,
                    cacheHitRatePercent: 44.4,
                    avgCostMicrodollars: 11_200,
                    avgDurationMs: 620,
                    avgStepsPerCall: 2.0,
                    totalToolCalls: 4,
                    activeUsers: 2,
                    activeWorkspaces: 1,
                    unpricedCalls: 1,
                },
                daily: sampleDays,
                byFeature: [
                    {
                        feature: 'assistant',
                        label: 'Farmwell Assistant',
                        calls: 5,
                        failedCalls: 1,
                        toolCalls: 4,
                        inputTokens: 22_500,
                        outputTokens: 3_000,
                        totalTokens: 25_500,
                        costMicrodollars: 56_000,
                        sharePercent: 100,
                    },
                ],
                byModel: [
                    {
                        provider: 'openai',
                        model: 'gpt-6.2-sol',
                        displayName: 'GPT-6.2 Sol',
                        calls: 5,
                        inputTokens: 22_500,
                        outputTokens: 3_000,
                        cacheReadTokens: 10_000,
                        totalTokens: 25_500,
                        costMicrodollars: 56_000,
                        sharePercent: 100,
                        inputPerMillion: 2,
                        outputPerMillion: 10,
                    },
                ],
                byUser: [
                    {
                        userId: 1,
                        name: 'Aminu Hussain',
                        email: 'aminu@example.test',
                        calls: 5,
                        inputTokens: 22_500,
                        outputTokens: 3_000,
                        totalTokens: 25_500,
                        avgCostMicrodollars: 11_200,
                        costMicrodollars: 56_000,
                        sharePercent: 100,
                    },
                ],
                byWorkspace: [
                    {
                        teamId: 4,
                        name: 'MDSxclusive Farms',
                        slug: 'mdsxclusive-farms',
                        planLabel: 'Investor / Pilot',
                        activeUsers: 2,
                        calls: 5,
                        totalTokens: 25_500,
                        costMicrodollars: 56_000,
                        sharePercent: 100,
                    },
                ],
                recentCalls: {
                    data: [
                        {
                            id: 10,
                            feature: 'assistant',
                            featureLabel: 'Farmwell Assistant',
                            provider: 'openai',
                            model: 'gpt-6.2-sol',
                            status: 'completed',
                            isStreamed: true,
                            stepsCount: 2,
                            toolCallsCount: 1,
                            inputTokens: 20_000,
                            cacheReadTokens: 10_000,
                            outputTokens: 2_000,
                            totalTokens: 22_000,
                            costMicrodollars: 41_000,
                            pricingStatus: 'priced',
                            durationMs: 500,
                            errorMessage: null,
                            occurredAt: '2026-11-27T18:00:00Z',
                            occurredAtFormatted: 'Nov 27, 18:00',
                            user: {
                                id: 1,
                                name: 'Musa D Suleiman',
                                email: null,
                            },
                            impersonator: { id: 2, name: 'Aminu Hussain' },
                            team: {
                                id: 4,
                                name: 'MDSxclusive Farms',
                                slug: 'mdsxclusive-farms',
                            },
                        },
                    ],
                    total: 1,
                    prev_page_url: null,
                    next_page_url: null,
                },
                filterOptions: {
                    workspaces: [
                        {
                            id: 4,
                            name: 'MDSxclusive Farms',
                            slug: 'mdsxclusive-farms',
                        },
                    ],
                    features: [
                        { value: 'assistant', label: 'Farmwell Assistant' },
                    ],
                    models: ['gpt-6.2-sol'],
                    statuses: [{ value: 'completed', label: 'Completed' }],
                },
                catalog: {
                    syncedModelsCount: 112,
                    lastSyncedAt: '2026-11-27T17:00:00Z',
                    unpricedModels: [
                        { provider: 'openai', model: 'unpriced-experimental' },
                    ],
                    activeModels: {
                        assistant: 'gpt-6.2-sol',
                        summary: 'gpt-6.2-sol',
                    },
                },
                errors: {},
            },
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => AiUsagePage,
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) }).use(plugin),
        render: renderToString,
    });

    assert.match(populatedPage.body, /AI Usage Analytics/);
    assert.match(populatedPage.body, /gpt-6\.2-sol/);
    assert.match(populatedPage.body, /\$0\.0560/);
    assert.match(populatedPage.body, /unpriced-experimental/);
    assert.match(populatedPage.body, /Musa D Suleiman/);
    assert.match(populatedPage.body, /via Aminu Hussain/);
    assert.match(populatedPage.body, /role="region"/);
    assert.match(populatedPage.body, /scope="col"/);
});
