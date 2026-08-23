<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Support\Theme\Theme::name() }}</title>

        <!-- Scripts / Styles / Fonts (Vite が自前配信) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.theme')
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
            <a href="/" class="flex flex-col items-center gap-3 text-center">
                <x-application-logo class="h-14 w-14 text-2xl" />

                <span>
                    <span class="block text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ \App\Support\Theme\Theme::name() }}
                    </span>
                    @if (\App\Support\Theme\Theme::tagline() !== '')
                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                            {{ \App\Support\Theme\Theme::tagline() }}
                        </span>
                    @endif
                </span>
            </a>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
