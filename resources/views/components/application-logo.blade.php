@php
    $logoUrl = \App\Support\Theme\Theme::logoUrl();
@endphp

@if ($logoUrl)
    <img src="{{ $logoUrl }}" alt="{{ \App\Support\Theme\Theme::name() }}"
         {{ $attributes->merge(['class' => 'h-9 w-auto object-contain']) }}>
@else
    {{-- ロゴ未設定時は、サービス名の頭文字を使ったマークを表示する --}}
    <span {{ $attributes->merge([
        'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-base font-bold text-white',
    ]) }}>
        {{ \App\Support\Theme\Theme::initial() }}
    </span>
@endif
