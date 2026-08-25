@props(['table'])

@php
    /** @var \App\Support\DataTable\Table $table */
    $views = $table->savedViews();
    $active = $table->activeView();
    $conditions = $table->state->conditions();
    $indexPath = parse_url($table->indexUrl(), PHP_URL_PATH).($conditions === [] ? '' : '?'.http_build_query($conditions));
@endphp

{{--
    保存ビュー(マイビュー)。

    よく使う絞り込みの組み合わせに名前を付けて保存し、プルダウンから呼び出す。
    呼び出しは一覧に ?view=<id> を付けるだけなので、サマリも CSV も同じ条件で動く。

        <x-saved-views :table="$table" />

    TableDefinition::savedViews() が true の一覧だけで描画される。
--}}
<div class="flex flex-wrap items-center gap-2" x-data="{ open: false }" @keydown.escape.stop="open = false">
    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">ビュー</span>

    <div class="relative" @click.outside="open = false">
        <button type="button"
                @click="open = ! open"
                aria-haspopup="menu"
                :aria-expanded="open.toString()"
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary motion-reduce:transition-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
            <span>{{ $active?->name ?? 'すべて' }}</span>
            @if ($active?->is_default)
                <x-badge tone="primary">既定</x-badge>
            @endif
            <svg class="h-4 w-4 fill-current text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <div x-show="open"
             x-cloak
             x-transition.opacity.duration.100ms
             role="menu"
             class="absolute z-30 mt-1 w-72 overflow-hidden rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-700 dark:bg-gray-800">

            <a href="{{ $table->resetUrl() }}" role="menuitem"
               @class([
                   'block px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700',
                   'bg-primary-soft text-primary-soft-fg' => $active === null,
                   'text-gray-700 dark:text-gray-200' => $active !== null,
               ])>
                すべて<span class="ms-2 text-xs text-gray-500 dark:text-gray-400">条件なし</span>
            </a>

            @forelse ($views as $view)
                <div @class([
                    'flex items-center gap-1 px-1',
                    'bg-primary-soft' => $active?->id === $view->id,
                ])>
                    <a href="{{ $table->viewUrl($view) }}" role="menuitem"
                       @class([
                           'flex-1 truncate rounded px-2 py-2 hover:bg-gray-50 dark:hover:bg-gray-700',
                           'text-primary-soft-fg' => $active?->id === $view->id,
                           'text-gray-700 dark:text-gray-200' => $active?->id !== $view->id,
                       ])>
                        {{ $view->name }}
                        @if ($view->is_default)
                            <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">（既定）</span>
                        @endif
                    </a>

                    <form method="POST" action="{{ route('saved-views.destroy', $view->id) }}"
                          onsubmit="return confirm('ビュー「{{ $view->name }}」を削除しますか?')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="redirect_to" value="{{ $indexPath }}">
                        <button type="submit"
                                class="rounded p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-rose-600 motion-reduce:transition-none dark:hover:bg-gray-700"
                                aria-label="ビュー「{{ $view->name }}」を削除">
                            <x-icon name="close" class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            @empty
                <p class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                    保存したビューはまだありません。
                </p>
            @endforelse

            <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

            <button type="button" role="menuitem"
                    @click="open = false; $dispatch('open-modal', 'saved-view-form')"
                    class="block w-full px-3 py-2 text-start text-primary-text hover:bg-gray-50 dark:hover:bg-gray-700">
                現在の条件を保存…
            </button>
        </div>
    </div>

    @if ($conditions !== [])
        <span class="text-xs text-gray-500 dark:text-gray-400">
            現在 {{ count($conditions) }} 件の条件で絞り込み中
        </span>
    @endif

    {{-- 保存フォーム --}}
    <x-modal name="saved-view-form" title="ビューを保存" size="sm">
        <form method="POST" action="{{ route('saved-views.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="table_key" value="{{ $table->definition->key() }}">
            <input type="hidden" name="redirect_to" value="{{ $indexPath }}">

            {{-- いま画面に出ている条件をそのまま保存する --}}
            @foreach ($conditions as $key => $value)
                <input type="hidden" name="conditions[{{ $key }}]" value="{{ $value }}">
            @endforeach

            <x-form.text name="name" label="ビュー名" required maxlength="100"
                         :value="$active?->name"
                         placeholder="例：今月クローズ予定の進行中"
                         help="同じ名前で保存すると上書きします。" />

            <x-form.checkbox name="is_default" label="既定のビューにする"
                             :checked="(bool) $active?->is_default"
                             help="条件を指定せずにこの一覧を開いたとき、自動で適用します。" />

            @if ($conditions === [])
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    いまは条件が 1 つも指定されていないので、「すべて」を表示するビューとして保存されます。
                </p>
            @else
                <div class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-900/40 dark:text-gray-400">
                    保存される条件：{{ implode(' / ', array_map(fn ($k, $v) => $k.'='.$v, array_keys($conditions), $conditions)) }}
                </div>
            @endif

            {{-- ボタンはフォームの中に置く(モーダルの footer スロットはフォームの外に出るため) --}}
            <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">キャンセル</x-button>
                <x-button type="submit">保存</x-button>
            </div>
        </form>
    </x-modal>
</div>
