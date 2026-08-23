<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\Theme\Theme::name() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
</head>
<body class="h-full font-sans antialiased bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-100">
    <div class="flex min-h-full flex-col">
        <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <span class="flex items-center gap-2">
                    <x-application-logo />
                    <span class="text-lg font-semibold tracking-tight">{{ \App\Support\Theme\Theme::name() }}</span>
                </span>

                <nav class="flex items-center gap-4 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" class="font-medium text-primary-text hover:text-primary-hover">
                            ダッシュボード
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                            ログイン
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="font-medium text-primary-text hover:text-primary-hover">
                                新規登録
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-6 py-10">
            <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h1 class="text-3xl font-bold tracking-tight">Business Template</h1>
                <p class="mt-3 text-gray-600 dark:text-gray-300">
                    業務システム共通基盤テンプレートの開発環境が正常に起動しています。
                </p>

                <dl class="mt-8 grid grid-cols-1 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 sm:grid-cols-3 dark:border-gray-700 dark:bg-gray-700">
                    @foreach ($status as $label => $value)
                        <div class="bg-white px-4 py-5 dark:bg-gray-800">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $label }}
                            </dt>
                            <dd class="mt-1 text-sm font-semibold">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div @class([
                    'mt-6 rounded-lg px-4 py-3 text-sm font-medium',
                    'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' => $database['connected'],
                    'bg-rose-50 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200' => ! $database['connected'],
                ])>
                    @if ($database['connected'])
                        データベース接続 OK &mdash; {{ $database['message'] }}
                    @else
                        データベース接続 NG &mdash; {{ $database['message'] }}
                    @endif
                </div>
            </div>
        </main>

        <footer class="border-t border-gray-200 py-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            {{ \App\Support\Theme\Theme::name() }}
            @if (\App\Support\Theme\Theme::tagline() !== '')
                &mdash; {{ \App\Support\Theme\Theme::tagline() }}
            @endif
        </footer>
    </div>
</body>
</html>
