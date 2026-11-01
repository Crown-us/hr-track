<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#ffffff">
        <link rel="preload" href="/fonts/inter-400.ttf" as="font" type="font/ttf" crossorigin>
        <link rel="preload" href="/fonts/inter-600.ttf" as="font" type="font/ttf" crossorigin>
        <link rel="stylesheet" href="/fonts/inter.css">
        @viteReactRefresh
        @vite('resources/js/app.tsx')
        <x-inertia::head />
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
