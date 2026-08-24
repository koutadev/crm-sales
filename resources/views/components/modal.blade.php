@props([
    'name' => null,
    'title' => null,
    'size' => 'md',
    'show' => false,
    'closable' => true,
    'maxWidth' => null,
    'focusable' => false,
])

@php
    // 旧 API(maxWidth)も受け取れるようにする
    $widths = [
        'sm' => 'sm:max-w-md',
        'md' => 'sm:max-w-2xl',
        'lg' => 'sm:max-w-4xl',
        'xl' => 'sm:max-w-2xl',
        '2xl' => 'sm:max-w-2xl',
    ];

    $width = $widths[$maxWidth ?? $size] ?? $widths['md'];

    // 送信してバリデーションエラーになったときに開き直す
    // (フォーム側に <x-modal-marker :name="…" /> を入れておく)
    $shouldShow = $show || ($name !== null && old('_modal') === $name);

    $titleId = $name !== null ? $name.'-title' : null;
@endphp

{{--
    モーダル。用途は 3 つ。

      詳細表示  : <x-modal name="employee-detail" title="社員の詳細"> … </x-modal>
      編集フォーム: フォームの中に <x-modal-marker name="…" /> を入れると、
                   バリデーションエラー時に開いた状態で戻る
      確認ダイアログ: <x-confirm-dialog> を使う(誤操作防止で closable=false)

    開閉は名前つきイベントで行う。
      $dispatch('open-modal', 'employee-detail')
      $dispatch('close')  … モーダルの中から閉じる
--}}
<div x-data="modal(@js(['name' => $name, 'show' => $shouldShow, 'closable' => $closable]))"
     x-on:open-modal.window="matches($event.detail) && open()"
     x-on:close-modal.window="matches($event.detail) && close(true)"
     x-on:close.stop="close(true)"
     x-on:keydown.escape.window="close()"
     x-show="show"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
     style="display: {{ $shouldShow ? 'block' : 'none' }};">

    {{-- オーバーレイ --}}
    <div x-show="show"
         x-on:click="close()"
         x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900/50"
         aria-hidden="true"></div>

    {{-- 本体 --}}
    <div x-ref="panel"
         x-show="show"
         x-on:keydown.tab="trap($event)"
         role="dialog"
         aria-modal="true"
         @if ($titleId) aria-labelledby="{{ $titleId }}" @endif
         tabindex="-1"
         x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         {{ $attributes->merge(['class' => 'relative mx-auto mb-6 w-full overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800 '.$width]) }}>

        @if ($title !== null)
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h2 id="{{ $titleId }}" class="text-base font-semibold text-gray-800 dark:text-gray-200">{{ $title }}</h2>

                @if ($closable)
                    <button type="button" x-on:click="close(true)"
                            class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 motion-reduce:transition-none dark:hover:bg-gray-700"
                            aria-label="閉じる">
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                @endif
            </div>
        @endif

        <div class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
