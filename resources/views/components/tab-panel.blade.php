@props(['name'])

{{-- x-tabs の中身。name が現在のタブと一致したときだけ表示する。 --}}
<div x-show="tab === @js((string) $name)" x-cloak role="tabpanel" {{ $attributes }}>
    {{ $slot }}
</div>
