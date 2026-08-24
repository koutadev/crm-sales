@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'disabled' => false,
    'help' => null,
    'inline' => true,
    'messages' => null,
])

@php
    use App\Support\Ui\Input;

    $current = old($name, $selected);
@endphp

{{-- ラジオ(選択肢のまとまり)。options は [値 => ラベル]。 --}}
<x-form.field :name="$name" :help="$help" :messages="$messages">
    <fieldset>
        @if ($label !== null)
            <legend class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $label }}
                @if ($required)
                    <span class="ms-1 text-xs font-normal text-rose-600 dark:text-rose-400">必須</span>
                @endif
            </legend>
        @endif

        <div @class(['flex gap-4' => $inline, 'space-y-2' => ! $inline])>
            @foreach ($options as $value => $optionLabel)
                <label for="{{ $name }}-{{ $value }}" class="inline-flex items-center gap-2">
                    <input type="radio"
                           id="{{ $name }}-{{ $value }}"
                           name="{{ $name }}"
                           value="{{ $value }}"
                           @checked((string) $current === (string) $value)
                           @required($required)
                           @disabled($disabled)
                           {{ $attributes->merge(['class' => Input::checkableClasses(rounded: false)]) }}>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $optionLabel }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
</x-form.field>
