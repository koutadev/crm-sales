@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'help' => null,
    'disabled' => false,
])

@php
    $current = (string) old($name, $selected);
@endphp

{{--
    セグメント(横並びのラジオ)。2〜4 個程度の切り替えに使う。

        <x-form.segment name="basis" label="基準日"
                        :options="['expected_close_date' => '予定クローズ日', 'ordered_at' => '受注日']"
                        selected="expected_close_date" />
--}}
<x-form.field :name="$name" :label="$label" :help="$help">
    <div class="inline-flex rounded-md border border-gray-300 p-0.5 dark:border-gray-700" role="radiogroup"
         @if ($label) aria-label="{{ $label }}" @endif>
        @foreach ($options as $value => $optionLabel)
            @php $id = $name.'-'.$value; @endphp

            <label for="{{ $id }}"
                   @class([
                       'cursor-pointer rounded px-3 py-1.5 text-xs font-medium transition-colors motion-reduce:transition-none',
                       'bg-primary text-white' => $current === (string) $value,
                       'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' => $current !== (string) $value,
                       'cursor-not-allowed opacity-50' => $disabled,
                   ])>
                <input type="radio"
                       id="{{ $id }}"
                       name="{{ $name }}"
                       value="{{ $value }}"
                       @checked($current === (string) $value)
                       @disabled($disabled)
                       class="sr-only"
                       {{ $attributes }}>
                {{ $optionLabel }}
            </label>
        @endforeach
    </div>
</x-form.field>
