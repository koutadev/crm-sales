@props([
    'name',
    'label' => null,
    'value' => '1',
    'checked' => false,
    'required' => false,
    'disabled' => false,
    'help' => null,
    'id' => null,
    'messages' => null,
])

@php
    use App\Support\Ui\Input;

    $inputId = $id ?? $name;
    // 未チェックだと送信されないため、old() が空でも初期値を尊重する
    $isChecked = (bool) old($name, $checked);
@endphp

{{-- チェックボックス(単体)。ラベルは右側に置く。 --}}
<x-form.field :name="$name" :help="$help" :messages="$messages">
    <label for="{{ $inputId }}" class="inline-flex items-center gap-2">
        <input type="checkbox"
               id="{{ $inputId }}"
               name="{{ $name }}"
               value="{{ $value }}"
               @checked($isChecked)
               @required($required)
               @disabled($disabled)
               {{ $attributes->merge(['class' => Input::checkableClasses()]) }}>

        @if ($label !== null)
            <span class="text-sm text-gray-700 dark:text-gray-300">
                {{ $label }}
                @if ($required)
                    <span class="ms-1 text-xs font-normal text-rose-600 dark:text-rose-400">必須</span>
                @endif
            </span>
        @endif
    </label>
</x-form.field>
