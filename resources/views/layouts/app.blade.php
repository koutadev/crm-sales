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
        @stack('head')
    </head>
    <body class="font-sans antialiased">
        {{--
            アプリの外枠。

            左サイドナビ + 上部バー(パンくず・ユーザーメニュー) + 本文。
            ナビの開閉状態は Alpine の appShell が持つ(resources/js/app-shell.js)。

            各画面は今までどおり <x-app-layout> の中身を書くだけでよい。
            パンくずの末尾に画面固有の見出しを足したい場合は breadcrumb スロットを渡す。

                <x-slot name="breadcrumb">新規登録</x-slot>
        --}}
        <div x-data="appShell()" @keydown.escape.window="closeMobile()" class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <x-app-sidebar />

            <div :class="collapsed ? 'lg:ps-[4.5rem]!' : ''"
                 class="flex min-h-screen flex-col transition-[padding] duration-200 motion-reduce:transition-none lg:ps-64">

                <x-app-topbar :trail="isset($breadcrumb) ? trim($breadcrumb) : null" />

                <!-- Page Heading -->
                @isset($header)
                    <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>

            <x-toast-container />
        </div>
    </body>
</html>
