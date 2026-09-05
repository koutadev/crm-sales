@props([
    'initialDetail' => null,
    'resourceLabel' => 'データ',
    'detailView' => 'masters._detail',
    'deletable' => true,
])

{{--
    一覧の行クリックで開く詳細モーダルと、削除の確認ダイアログ。

    x-master-index が中で使うので、各マスタの画面に書くことは何もない。
    行側は <x-table.row :detail-url="…"> を指定するだけ。

    共通マスタ以外の一覧(レッスン枠など)で使う場合は、モーダルの中身のビューを
    detail-view で差し替える。論理削除を使わない画面は :deletable="false" にすると
    削除の確認ダイアログを置かない。
--}}
<div x-data="masterDetail()" @open-detail.window="load($event.detail)">
    <x-modal name="master-detail" size="lg" :show="$initialDetail !== null">
        {{-- 読み込み中 / 失敗 --}}
        <div x-show="loading" x-cloak class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
            読み込み中…
        </div>

        <div x-show="failed" x-cloak class="py-10 text-center text-sm text-rose-600 dark:text-rose-400">
            詳細を読み込めませんでした。時間をおいて開き直してください。
        </div>

        @if ($initialDetail !== null)
            {{-- バリデーションエラーで戻ってきた場合は、サーバ側で描画した中身をそのまま出す --}}
            <div x-show="! loading && ! failed && content === ''">
                @include($detailView, $initialDetail)
            </div>
        @endif

        {{-- 取得した中身 --}}
        <div x-show="! loading && ! failed && content !== ''" x-html="content" x-cloak></div>
    </x-modal>
</div>

{{-- 削除の確認。対象は開くときに渡すので、一覧に 1 つあればよい --}}
@if ($deletable)
<div x-data="{ action: '', label: '' }"
     @open-delete.window="action = $event.detail.action; label = $event.detail.label;
                          $nextTick(() => $dispatch('open-modal', 'master-delete'))">
    <x-modal name="master-delete" title="削除しますか？" size="sm" :closable="false">
        <p>
            <span class="font-medium" x-text="label"></span> を削除します。
        </p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            論理削除のためデータは残ります（管理者は削除済みの表示・復元ができます）。
        </p>

        <x-slot name="footer">
            <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">キャンセル</x-button>

            <form method="POST" :action="action">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger">削除する</x-button>
            </form>
        </x-slot>
    </x-modal>
</div>
@endif
