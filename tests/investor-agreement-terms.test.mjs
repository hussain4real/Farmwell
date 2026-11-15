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

function agreement(overrides = {}) {
    return {
        id: 1,
        title: 'Rice and pepper agreement',
        investorName: 'Test investor',
        farmName: 'Crop farm',
        currency: 'NGN',
        statusLabel: 'Active',
        amountCommitted: '1144500.00',
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
