import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeInstallPrompt } from '../resources/js/lib/installApp.ts';

function setup(standalone = false) {
    const browser = new EventTarget();
    const install = new EventTarget();
    const dismiss = new EventTarget();
    const banner = {
        hidden: true,
        querySelector: (selector) =>
            ({
                '[data-install]': install,
                '[data-dismiss]': dismiss,
            })[selector],
    };
    browser.matchMedia = () => ({ matches: standalone });
    globalThis.window = browser;
    globalThis.document = { getElementById: () => banner };

    initializeInstallPrompt();

    const offerInstall = (prompt = async () => {}) =>
        browser.dispatchEvent(
            Object.assign(
                new Event('beforeinstallprompt', { cancelable: true }),
                { prompt },
            ),
        );

    return { browser, banner, install, dismiss, offerInstall };
}

test('an eligible browser can open its native installation prompt once', () => {
    const { banner, install, offerInstall } = setup();
    let calls = 0;
    assert.equal(banner.hidden, true);
    assert.equal(
        offerInstall(async () => {
            calls++;
        }),
        false,
    );
    assert.equal(banner.hidden, false);

    install.dispatchEvent(new Event('click'));
    install.dispatchEvent(new Event('click'));

    assert.equal(calls, 1);
    assert.equal(banner.hidden, true);
});

test('installation and dismissal hide the banner for the rest of the page visit', () => {
    for (const action of ['installed', 'dismissed']) {
        const { browser, banner, dismiss, offerInstall } = setup();
        offerInstall();
        assert.equal(banner.hidden, false);

        if (action === 'installed') {
            browser.dispatchEvent(new Event('appinstalled'));
        } else {
            dismiss.dispatchEvent(new Event('click'));
        }

        offerInstall();
        assert.equal(banner.hidden, true);
    }
});

test('the installed app leaves the install banner hidden', () => {
    const { banner, offerInstall } = setup(true);
    assert.equal(offerInstall(), true);
    assert.equal(banner.hidden, true);
});
