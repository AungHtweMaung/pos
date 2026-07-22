<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Grocery POS') }}</title>

        {{-- Apply the persisted / OS-preferred theme before paint to avoid a
             flash of the wrong mode (spec §7). --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var theme = stored
                        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                    document.documentElement.setAttribute('data-bs-theme', theme);
                } catch (e) {
                    document.documentElement.setAttribute('data-bs-theme', 'light');
                }
            })();
        </script>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
