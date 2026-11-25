import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createInertiaApp } from '@inertiajs/vue3';
import vue from '@vitejs/plugin-vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import { createSSRApp, defineComponent, h } from 'vue';

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

for (const layout of ['AppSidebar', 'AppHeader']) {
    test(`${layout} renders farm navigation without developer links`, async () => {
        const { default: Navigation } = await server.ssrLoadModule(
            `/resources/js/components/${layout}.vue`,
        );
        const { default: SidebarProvider } = await server.ssrLoadModule(
            '/resources/js/components/ui/sidebar/SidebarProvider.vue',
        );
        const Page = defineComponent({
            render: () =>
                h(SidebarProvider, null, { default: () => h(Navigation) }),
        });
        const result = await createInertiaApp({
            page: {
                component: 'NavigationTest',
                url: '/test-farm/dashboard',
                version: 'test',
                props: {
                    auth: {
                        user: {
                            id: 1,
                            name: 'Farm Admin',
                            email: 'admin@example.test',
                        },
                    },
                    currentTeam: {
                        id: 1,
                        name: 'Test Farm',
                        slug: 'test-farm',
                        role: 'admin',
                    },
                    currentTeamPermissions: {
                        canViewFarmOperations: true,
                        canManageFieldWork: true,
                    },
                    teams: [],
                    errors: {},
                },
                clearHistory: false,
                encryptHistory: false,
            },
            resolve: () => Page,
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) }).use(plugin),
            render: renderToString,
        });

        assert.match(result.body, /Farmwell/);
        assert.match(result.body, /Field Diary/);
        assert.match(result.body, /href="\/test-farm\/field-diary"/);
        assert.doesNotMatch(
            result.body,
            /Repository|Documentation|laravel\.com|github\.com/,
        );
    });
}
