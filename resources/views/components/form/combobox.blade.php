@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'source' => null,
    'size' => 'md',
    'required' => false,
    'disabled' => false,
    'help' => null,
    'placeholder' => '入力して絞り込み',
    'empty' => '該当する候補がありません',
    'id' => null,
    'messages' => null,
])

@php
    use App\Support\Ui\Input;
    use App\Support\Ui\SearchText;
    use App\Support\Ui\Size;

    $inputId = $id ?? $name;
    // messages を明示したときはそちらを優先する(カタログや独自表示用)
    $hasError = $messages !== null ? $messages !== [] : $errors->has($name);
    $current = old($name, $selected);

    // [値 => ラベル] を JS が扱いやすい形に直す
    $items = [];

    foreach ($options as $value => $optionLabel) {
        $items[] = [
            'value' => (string) $value,
            'label' => (string) $optionLabel,
            // ひらがな/カタカナ・全角半角・大文字小文字を揃えた検索用の文字列
            'search' => SearchText::normalize((string) $optionLabel),
        ];
    }

    $currentLabel = '';

    foreach ($items as $item) {
        if ($item['value'] === (string) $current) {
            $currentLabel = $item['label'];
        }
    }

    $config = [
        'options' => $items,
        'value' => $current === null ? '' : (string) $current,
        'label' => $currentLabel,
        'source' => $source,
    ];
@endphp

{{--
    コンボボックス（入力で候補を絞るセレクト）。

    静的モード（候補をそのまま渡す）:
        <x-form.combobox name="partner_id" label="顧客" :options="$customers" :selected="$partnerId" />

    非同期モード（入力に応じてサーバへ問い合わせる）:
        <x-form.combobox name="partner_id" label="顧客" :source="route('customers.options')" />
        エンドポイントは ?q=... を受け取り [{ value, label }] を返す。
--}}
<x-form.field :name="$name" :label="$label" :for="$inputId" :required="$required" :help="$help" :messages="$messages">
    <div x-data="combobox(@js($config))"
         @keydown.escape.stop="close()"
         @click.outside="close()"
         @if ($source) data-source="{{ $source }}" @endif
         class="relative">

        {{-- 実際に送信される値 --}}
        <input type="hidden" name="{{ $name }}" :value="value">

        <div class="relative">
            <input type="text"
                   id="{{ $inputId }}"
                   x-ref="input"
                   role="combobox"
                   autocomplete="off"
                   aria-autocomplete="list"
                   aria-controls="{{ $inputId }}-listbox"
                   :aria-expanded="open.toString()"
                   :aria-activedescendant="activeDescendant('{{ $inputId }}')"
                   placeholder="{{ $placeholder }}"
                   x-model="query"
                   @focus="openList(); $el.select()"
                   @input="onInput()"
                   @keydown.arrow-down.prevent="move(1)"
                   @keydown.arrow-up.prevent="move(-1)"
                   @keydown.home.prevent="moveTo(0)"
                   @keydown.end.prevent="moveTo(-1)"
                   @keydown.enter.prevent="selectActive()"
                   @keydown.tab="close()"
                   @required($required)
                   @disabled($disabled)
                   @if ($hasError) aria-invalid="true" @endif
                   {{ $attributes->merge(['class' => Input::classes(Size::resolve($size), $hasError).' pe-16']) }}>

            {{-- クリア / 開閉 --}}
            <div class="absolute inset-y-0 end-0 flex items-center gap-0.5 pe-2">
                <button type="button"
                        x-show="hasSelection"
                        x-cloak
                        @click="clear(); $refs.input?.focus()"
                        @disabled($disabled)
                        class="rounded p-1 text-gray-400 transition hover:text-gray-600 motion-reduce:transition-none dark:hover:text-gray-200"
                        aria-label="選択を解除">
                    <x-icon name="close" class="h-4 w-4" />
                </button>

                <span class="pointer-events-none text-gray-400" aria-hidden="true">
                    <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </span>
            </div>
        </div>

        {{-- 候補 --}}
        <ul id="{{ $inputId }}-listbox"
            role="listbox"
            x-ref="list"
            x-show="open"
            x-cloak
            x-transition.opacity.duration.100ms
            class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-700 dark:bg-gray-800">

            <template x-for="(item, index) in filtered" :key="item.value">
                <li :id="`{{ $inputId }}-option-${index}`"
                    role="option"
                    :aria-selected="(item.value === value).toString()"
                    @click="select(item)"
                    @mouseenter="activeIndex = index"
                    :class="index === activeIndex
                        ? 'bg-primary-soft text-primary-soft-fg'
                        : 'text-gray-700 dark:text-gray-200'"
                    class="cursor-pointer px-3 py-2">
                    <span x-text="item.label"></span>
                    <span class="ms-2 text-xs text-primary-text" x-show="item.value === value">選択中</span>
                </li>
            </template>

            {{-- 読み込み中 / 失敗 / 該当なし --}}
            <li x-show="loading" x-cloak class="px-3 py-2 text-gray-500 dark:text-gray-400">読み込み中…</li>
            <li x-show="failed" x-cloak class="px-3 py-2 text-rose-600 dark:text-rose-400">候補を取得できませんでした</li>
            <li x-show="! loading && ! failed && filtered.length === 0" x-cloak
                class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $empty }}</li>
        </ul>
    </div>
</x-form.field>
