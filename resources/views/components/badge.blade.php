@props([
    'tone' => 'neutral',
    'dot' => false,
])

@php
    use App\Support\Ui\Tone;

    $resolvedTone = Tone::resolve($tone);
@endphp

{{-- バッジ / ステータスチップ --}}
<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium '.$resolvedTone->badgeClasses(),
]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $resolvedTone->dotClasses() }}" aria-hidden="true"></span>
    @endif

    {{ $slot }}
</span>
