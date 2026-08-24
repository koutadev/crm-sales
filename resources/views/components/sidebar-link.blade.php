@props(['item'])

@php
    /** @var \App\Support\Navigation\NavItem $item */
    $active = $item->isActive();
@endphp

{{--
    サイドナビの項目 1 つ。
    折りたたみ中(collapsed)はアイコンだけになるため、ラベルを title / sr-only で補う。
--}}
<a href="{{ $item->url() }}"
   @if ($active) aria-current="page" @endif
   :title="collapsed ? '{{ $item->label }}' : null"
   @class([
       'group flex items-center gap-3 rounded-md border-s-2 px-3 py-2 text-sm transition-colors motion-reduce:transition-none',
       'border-primary bg-primary-soft font-semibold text-primary-soft-fg' => $active,
       'border-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white' => ! $active,
   ])>
    <x-icon :name="$item->icon" class="h-5 w-5 shrink-0" />

    <span class="truncate" x-show="! collapsed">{{ $item->label }}</span>
    <span class="sr-only" x-show="collapsed" x-cloak>{{ $item->label }}</span>
</a>
