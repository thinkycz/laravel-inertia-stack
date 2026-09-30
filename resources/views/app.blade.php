<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        <script>
            let appearance;
            try {
                appearance = localStorage.getItem('appearance');
            } catch (_) {}
            try {
                document.documentElement.classList.toggle('dark', appearance === 'dark' || (appearance !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches));
            } catch (_) {}
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head />
    </head>
    <body class="bg-surface-bg text-on-surface font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
