type InstallPromptEvent = Event & { prompt: () => Promise<unknown> };

export function isStandaloneApp(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        ('standalone' in navigator && navigator.standalone === true)
    );
}

export function initializeInstallPrompt(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const banner = document.getElementById('farmwell-install');
    const install = banner?.querySelector('[data-install]');
    const dismiss = banner?.querySelector('[data-dismiss]');

    if (!banner || !install || !dismiss || isStandaloneApp()) {
        return;
    }

    let pendingPrompt: InstallPromptEvent | null = null;
    let dismissed = false;

    const hide = () => {
        pendingPrompt = null;
        banner.hidden = true;
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();

        if (
            !dismissed &&
            'prompt' in event &&
            typeof event.prompt === 'function'
        ) {
            pendingPrompt = event as InstallPromptEvent;
            banner.hidden = false;
        }
    });

    install.addEventListener('click', async () => {
        const prompt = pendingPrompt;
        hide();

        try {
            await prompt?.prompt();
        } catch {
            // The browser's install menu remains available if its prompt expires.
        }
    });

    const dismissPrompt = () => {
        dismissed = true;
        hide();
    };

    dismiss.addEventListener('click', dismissPrompt);
    window.addEventListener('appinstalled', dismissPrompt);
}
