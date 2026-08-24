@props([
    'name' => null,
    'label' => null,
    'for' => null,
    'required' => false,
    'help' => null,
    'messages' => null,
])

@php
    // 明示的に渡されなければ、フィールド名からバリデーションエラーを引く
    $items = $messages ?? ($name !== null ? $errors->get($name) : []);
@endphp

{{--
    入力欄の枠(ラベル・必須マーク・ヘルプ・エラー)。
    各入力部品がこれを内側で使うので、画面から直接使うことは少ない。
--}}
<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if ($label !== null)
        <label @if ($for) for="{{ $for }}" @endif class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $label }}
            @if ($required)
                <span class="ms-1 text-xs font-normal text-rose-600 dark:text-rose-400">必須</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($help !== null)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif

    @foreach ($items as $message)
        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @endforeach
</div>
