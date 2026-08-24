@props([
    'tabs' => [],
    'active' => null,
    'sync' => false,
])

@php
    $keys = array_keys($tabs);
    $default = $active ?? ($keys[0] ?? '');

    // sync のときは ?tab=... を初期値にする(定義済みのキーだけ受け付ける)
    $initial = $sync && in_array((string) request('tab'), array_map('strval', $keys), true)
        ? (string) request('tab')
        : $default;
@endphp

{{--
    タブ。

        <x-tabs :tabs="['overview' => '概要', 'items' => '明細']">
            <x-tab-panel name="overview">…</x-tab-panel>
            <x-tab-panel name="items">…</x-tab-panel>
        </x-tabs>
--}}
<div x-data="{ tab: @js($initial), keys: @js(array_map('strval', $keys)),
        move(step) {
            const index = this.keys.indexOf(this.tab);
            this.tab = this.keys[(index + step + this.keys.length) % this.keys.length];
        } }"
     {{ $attributes }}>
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex flex-wrap gap-6" role="tablist"
             @keydown.arrow-right.prevent="move(1)" @keydown.arrow-left.prevent="move(-1)">
            @foreach ($tabs as $key => $label)
                <button type="button"
                        role="tab"
                        :aria-selected="(tab === @js((string) $key)).toString()"
                        :tabindex="tab === @js((string) $key) ? 0 : -1"
                        @click="tab = @js((string) $key)"
                        :class="tab === @js((string) $key)
                            ? 'border-primary text-gray-900 dark:text-gray-100'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors motion-reduce:transition-none">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="pt-4">
        {{ $slot }}
    </div>
</div>
