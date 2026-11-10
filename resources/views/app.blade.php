<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#214d40">
        <link rel="manifest" href="/manifest.webmanifest" crossorigin="use-credentials">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/farmwell-192.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
        <aside
            id="farmwell-install"
            aria-label="Install Farmwell"
            hidden
            class="fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+0.75rem)] z-40 mx-auto max-w-sm rounded-xl border bg-background p-4 text-foreground shadow-lg"
        >
            <div class="flex items-center gap-3">
                <img src="/farmwell-192.png" alt="" width="40" height="40" class="rounded-lg">
                <div>
                    <p class="font-semibold">Install Farmwell</p>
                    <p class="text-sm text-muted-foreground">Open it from your home screen.</p>
                </div>
            </div>
            <div class="mt-3 flex justify-end gap-2">
                <button type="button" data-dismiss class="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted">
                    Not now
                </button>
                <button type="button" data-install class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    Install
                </button>
            </div>
        </aside>
    </body>
</html>
