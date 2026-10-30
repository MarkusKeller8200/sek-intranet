<!DOCTYPE html>
<html lang="de-CH" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        {{-- Seite ist deutsch (Schweiz): Browser sollen keine Übersetzung aus dem Englischen anbieten --}}
        <meta http-equiv="content-language" content="de-CH">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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

            // Gewählte Schrift sofort setzen (siehe resources/js/hooks/use-font.ts), damit nichts springt
            (function() {
                try {
                    const stacks = {
                        atkinson: "'Atkinson Hyperlegible', ui-sans-serif, system-ui, sans-serif",
                        inter: "'Inter', ui-sans-serif, system-ui, sans-serif",
                        serif: "'Source Serif 4', Georgia, 'Times New Roman', serif",
                        system: "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
                    };
                    // Andere Sprache: Seite kurz verdecken, bis übersetzt ist (höchstens 1,5 s)
                    const lang = localStorage.getItem('lang');
                    // Gewählte Sprache sofort angeben (vor der Spracherkennung des Browsers)
                    if (lang === 'en' || lang === 'gsw-u-sd-chzh') {
                        document.documentElement.lang = lang === 'en' ? 'en' : 'gsw-CH';
                    }
                    if (lang && lang !== 'de-CH') {
                        document.documentElement.classList.add('i18n-wait');
                        setTimeout(function () { document.documentElement.classList.remove('i18n-wait'); }, 1500);
                    }
                    const font = localStorage.getItem('font');
                    if (font && stacks[font]) {
                        document.documentElement.style.setProperty('--font-sans', stacks[font]);
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #ffffff;
            }

            html.dark {
                background-color: #131516;
            }

            html.i18n-wait body {
                visibility: hidden;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        {{-- PWA: als App installierbar (Manifest, Farben, iOS) --}}
        <link rel="manifest" href="/manifest.webmanifest" crossorigin="use-credentials">
        <meta name="theme-color" content="#0299d7">
        <meta name="app-build" content="{{ config('intranet.build') ?? 'dev' }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Intranet">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
