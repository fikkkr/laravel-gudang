<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['src/assets/css/app.css', 'src/assets/js/app.js'])
    </head>
    <body class="min-h-screen bg-white text-neutral-900 antialiased">
        <div class="dashboard-shell" data-dashboard-shell>
            @include('layouts.sidebar')
            <div class="dashboard-content">
                @include('layouts.content-header')
                <main class="dashboard-main dashboard-profile-main">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
