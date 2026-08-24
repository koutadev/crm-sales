@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'size' => 'md',
    'required' => false,
    'disabled' => false,
    'help' => null,
    'placeholder' => null,
    'id' => null,
    'messages' => null,
])

@php
    use App\Support\Ui\Input;
    use App\Support\Ui\Size;

    $inputId = $id ?? $name;
    // messages を明示したときはそちらを優先する(カタログや独自表示用)
    $hasError = $messages !== null ? $messages !== [] : $errors->has($name);
    $current = old($name, $selected);
@endphp

{{-- セレクト。options は [値 => ラベル]。 --}}
<x-form.field :name="$name" :label="$label" :for="$inputId" :required="$required" :help="$help" :messages="$messages">
    <select id="{{ $inputId }}"
            name="{{ $name }}"
            @required($required)
            @disabled($disabled)
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->merge(['class' => Input::classes(Size::resolve($size), $hasError)]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-form.field>
