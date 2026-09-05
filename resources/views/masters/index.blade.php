@php
    $user = auth()->user();
@endphp

{{-- マスタ管理のハブ。各マスタへの入口をカードで並べる。 --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                マスタ管理
            </h2>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                業務データが参照する共通のマスタです。件数は削除済みを除いた数です。
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-flash />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $card)
                    <x-card class="flex h-full flex-col">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary-soft-fg">
                                <x-icon :name="$card->icon" class="h-5 w-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <h3 class="truncate text-sm font-semibold text-gray-800 dark:text-gray-200">
                                        {{ $card->label }}
                                    </h3>

                                    <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="text-base font-bold tabular-nums text-gray-900 dark:text-gray-100">
                                            {{ number_format($counts[$card->key] ?? 0) }}
                                        </span>
                                        件
                                    </p>
                                </div>

                                <p class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                                    {{ $card->description }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                            <x-button size="sm" variant="secondary" :href="$card->indexUrl()">開く</x-button>

                            @if ($user?->can($card->managePermission()->value))
                                <x-button size="sm" variant="ghost" :href="$card->createUrl()">新規登録</x-button>
                            @endif
                        </div>
                    </x-card>
                @endforeach
            </div>

            @if ($cards === [])
                <div class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    表示できるマスタがありません。
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
