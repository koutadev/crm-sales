@props([
    'name',
    'label' => null,
    'value' => null,
    'size' => 'md',
    'required' => false,
    'disabled' => false,
    'help' => null,
    'min' => null,
    'max' => null,
    'step' => 1,
    'id' => null,
    'messages' => null,
])

@php
    use App\Support\Ui\Input;
    use App\Support\Ui\Size;

    $inputId = $id ?? $name;
    // messages を明示したときはそちらを優先する(カタログや独自表示用)
    $hasError = $messages !== null ? $messages !== [] : $errors->has($name);
@endphp

{{-- 数値入力。金額・数量など右寄せで扱う。 --}}
<x-form.field :name="$name" :label="$label" :for="$inputId" :required="$required" :help="$help" :messages="$messages">
    <input type="number"
           id="{{ $inputId }}"
           name="{{ $name }}"
           value="{{ old($name, $value) }}"
           step="{{ $step }}"
           @if ($min !== null) min="{{ $min }}" @endif
           @if ($max !== null) max="{{ $max }}" @endif
           @required($required)
           @disabled($disabled)
           @if ($hasError) aria-invalid="true" @endif
           {{ $attributes->merge(['class' => Input::classes(Size::resolve($size), $hasError).' text-right tabular-nums']) }}>
</x-form.field>
