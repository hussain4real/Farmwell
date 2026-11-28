import assert from 'node:assert/strict';
import test from 'node:test';

// Supply a mobile media query before loading VueUse, as a browser would.
let mediaListener;
const media = {
    matches: true,
    addEventListener(_event, listener) {
        mediaListener = listener;
    },
    removeEventListener() {},
};
globalThis.document = { createElement: () => ({}) };
globalThis.window = {
    document: globalThis.document,
    matchMedia: (query) => {
        assert.equal(query, '(max-width: 767px)');

        return media;
    },
};

const { createRenderer, createSSRApp, defineComponent, h, nextTick } =
    await import('vue');
const { renderToString } = await import('@vue/server-renderer');
const { useHydratedMediaQuery } =
    await import('../resources/js/composables/useHydratedMediaQuery.ts');

const renders = [];
const Sidebar = defineComponent({
    setup() {
        const isMobile = useHydratedMediaQuery('(max-width: 767px)');

        return () => {
            renders.push(isMobile.value);

            return h('div', isMobile.value ? 'mobile' : 'desktop');
        };
    },
});

const renderer = createRenderer({
    createElement: () => ({ children: [] }),
    createText: (text) => ({ text }),
    createComment: (text) => ({ text }),
    setText: (node, text) => {
        node.text = text;
    },
    setElementText: (node, text) => {
        node.text = text;
    },
    insert: (node, parent) => {
        parent.children.push(node);
    },
    remove: () => {},
    parentNode: () => null,
    nextSibling: () => null,
    patchProp: () => {},
});

test('sidebar uses the server layout for hydration and follows viewport changes after mount', async () => {
    assert.match(
        await renderToString(createSSRApp(Sidebar)),
        />desktop<\/div>/,
    );
    renders.length = 0;
    const root = { children: [] };
    const app = renderer.createApp(Sidebar);
    app.mount(root);
    await nextTick();

    assert.deepEqual(renders, [false, true]);
    assert.equal(root.children[0].text, 'mobile');
    mediaListener({ matches: false });
    await nextTick();
    assert.equal(root.children[0].text, 'desktop');
    app.unmount();
});
