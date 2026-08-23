@props([
    'name',
    'label',
    'required' => false,
    'help' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
        @if ($required)
            <span class="ms-1 text-xs font-normal text-rose-600 dark:text-rose-400">必須</span>
        @endif
    </label>

    {{ $slot }}

    @if ($help)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
