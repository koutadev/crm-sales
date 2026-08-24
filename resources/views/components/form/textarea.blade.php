@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 3,
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
@endphp

{{-- 複数行のテキスト入力 --}}
<x-form.field :name="$name" :label="$label" :for="$inputId" :required="$required" :help="$help" :messages="$messages">
    <textarea id="{{ $inputId }}"
              name="{{ $name }}"
              rows="{{ $rows }}"
              @if ($placeholder) placeholder="{{ $placeholder }}" @endif
              @required($required)
              @disabled($disabled)
              @if ($hasError) aria-invalid="true" @endif
              {{ $attributes->merge(['class' => Input::classes(Size::resolve($size), $hasError)]) }}>{{ old($name, $value) }}</textarea>
</x-form.field>
