@props([
    'title' => null,
    'subtitle' => null,
])

{{--
    カード。見出し・本文・アクション領域を持つ枠。

        <x-card title="社員" subtitle="全 32 名">
            <x-slot name="actions"><x-button size="sm">追加</x-button></x-slot>
            本文
            <x-slot name="footer">補足</x-slot>
        </x-card>
--}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800']) }}>
    @if ($title !== null || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
            <div class="min-w-0">
                @if ($title !== null)
                    <h3 class="truncate text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $title }}</h3>
                @endif

                @if ($subtitle !== null)
                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-gray-100 bg-gray-50 px-5 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
            {{ $footer }}
        </div>
    @endisset
</div>
