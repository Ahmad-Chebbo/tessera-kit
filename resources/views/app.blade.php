<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <script>
            (function () {
                var theme = 'light';
                try {
                    theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
                } catch (error) {
                    theme = 'light';
                }
                document.documentElement.setAttribute('data-theme', theme);
            })();
        </script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=El+Messiri:wght@400;500;600;700&family=Geist:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

        @routes
        @vite(array_filter(['resources/js/app.js', app(\Tessera\Engine\PageSource::class)->pathFor($page['component'])]))
        @inertiaHead
    </head>
    <body class="bg-bg font-sans text-fg antialiased">
        @inertia
    </body>
</html>
