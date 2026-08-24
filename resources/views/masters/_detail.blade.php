@php
    /** @var \App\Models\BaseModel $record */
    $updateUrl = route($routeName.'.update', $record->id);
    $deleteUrl = route($routeName.'.destroy', $record->id);
    $restoreUrl = route($routeName.'.restore', $record->id);

    // 直前の送信がこのレコードの編集だった場合は、編集フォームを開いた状態で戻す
    $openEditor = (string) old('_modal_record') === (string) $record->id && $errors->any();
@endphp

{{--
    一覧の行クリックで開くモーダルの中身。

    詳細と編集フォームを 1 つに収め、Alpine で切り替える。
    保存はふつうのフォーム送信で、失敗したら一覧に戻ってモーダルを開き直す
    (フォームの中の _modal / _modal_record が目印)。
--}}
<div x-data="{ editing: {{ $openEditor ? 'true' : 'false' }} }">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $detailTitle }}</p>

        <div class="flex items-center gap-2">
            @if ($record->trashed())
                <x-badge tone="danger">削除済み</x-badge>
            @else
                <x-badge :tone="$record->is_active ? 'success' : 'neutral'">{{ $record->activeLabel() }}</x-badge>
            @endif
        </div>
    </div>

    {{-- 詳細 --}}
    <div x-show="! editing">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
            @foreach ($rows as $label => $value)
                <div>
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
            <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">閉じる</x-button>

            @if ($canManage)
                @if ($record->trashed())
                    @if (auth()->user()?->isAdmin())
                        <form method="POST" action="{{ $restoreUrl }}">
                            @csrf
                            <x-button type="submit">復元する</x-button>
                        </form>
                    @endif
                @else
                    <x-button type="button" variant="danger"
                              x-on:click="$dispatch('close'); $nextTick(() => window.dispatchEvent(new CustomEvent('open-delete', { detail: { action: '{{ $deleteUrl }}', label: @js($detailTitle) } })))">
                        削除
                    </x-button>

                    <x-button type="button" x-on:click="editing = true">編集</x-button>
                @endif
            @endif
        </div>
    </div>

    {{-- 編集フォーム --}}
    @if ($canManage && ! $record->trashed())
        <div x-show="editing" x-cloak>
            <form method="POST" action="{{ $updateUrl }}" id="master-detail-form" class="space-y-5">
                @csrf
                @method('PUT')

                {{-- エラーで戻ってきたときに、このモーダル・このレコードを開き直すための目印 --}}
                <x-modal-marker name="master-detail" />
                <input type="hidden" name="_modal_record" value="{{ $record->id }}">

                @include($fieldsView)
            </form>

            <div class="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                <x-button type="button" variant="secondary" x-on:click="editing = false">詳細に戻る</x-button>
                <x-button type="submit" form="master-detail-form">保存</x-button>
            </div>
        </div>
    @endif
</div>
