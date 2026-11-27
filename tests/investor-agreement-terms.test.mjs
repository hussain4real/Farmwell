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
