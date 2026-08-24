@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'disabled' => false,
    'loading' => false,
    'icon' => null,
])

@php
    use App\Support\Ui\Size;
    use App\Support\Ui\Variant;

    $resolvedVariant = Variant::resolve($variant, Variant::Primary);
    $resolvedSize = Size::resolve($size);

    // ローディング中は押せない
    $isDisabled = $disabled || $loading;

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-md border font-medium',
        'transition-colors duration-150 motion-reduce:transition-none',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900',
        $resolvedVariant->buttonClasses(),
        $resolvedSize->buttonClasses(),
        $isDisabled ? 'pointer-events-none opacity-50' : '',
    ]);
@endphp

@if ($href !== null)
    <a href="{{ $isDisabled ? '#' : $href }}"
       @if ($isDisabled) aria-disabled="true" tabindex="-1" @endif
       {{ $attributes->merge(['class' => $classes]) }}>
        @include('components.partials.button-content')
    </a>
@else
    <button type="{{ $type }}"
            @disabled($isDisabled)
            @if ($loading) aria-busy="true" @endif
            {{ $attributes->merge(['class' => $classes]) }}>
        @include('components.partials.button-content')
    </button>
@endif
