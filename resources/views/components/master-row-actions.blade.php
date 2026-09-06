@props([
    'record',
    'routeName',
    'resourceLabel',
    // 登録・編集に必要な権限。マスタ単位に権限を分ける場合だけ渡す
    'managePermission' => \App\Enums\PermissionName::MasterManage->value,
])

{{--
    一覧の「操作」列。権限と削除状態に応じて出し分ける。

    削除・復元の確認は自前のダイアログ（x-confirm-dialog）で行う。
    data-open-modal はどこからでも効く（Alpine のスコープに依存しない）。
    画面が狭いときは、この列がカードの下段にまとまる（data-actions）。
--}}
<td data-actions class="whitespace-nowrap px-4 py-3 text-right">
    @can($managePermission)
        @if ($record->trashed())
            {{-- 復元は管理者のみ --}}
            @if (auth()->user()?->isAdmin())
                <button type="button"
                        data-open-modal="master-restore-{{ $record->id }}"
                        class="inline-flex min-h-11 items-center text-xs font-medium text-emerald-600 transition hover:text-emerald-500 motion-reduce:transition-none sm:min-h-0 dark:text-emerald-400">
                    復元
                </button>

                <x-confirm-dialog name="master-restore-{{ $record->id }}"
                                  title="{{ $resourceLabel }}を復元しますか？"
                                  :action="route($routeName.'.restore', $record->id)"
                                  confirm="復元する"
                                  variant="primary">
                    削除済みの{{ $resourceLabel }}を元に戻します。
                </x-confirm-dialog>
            @else
                <span class="text-xs text-gray-400">&mdash;</span>
            @endif
        @else
            <a href="{{ route($routeName.'.edit', $record->id) }}"
               class="inline-flex min-h-11 items-center text-xs font-medium text-primary-text hover:text-primary-hover sm:min-h-0">
                編集
            </a>

            <button type="button"
                    data-open-modal="master-delete-{{ $record->id }}"
                    class="ms-3 inline-flex min-h-11 items-center text-xs font-medium text-rose-600 transition hover:text-rose-500 motion-reduce:transition-none sm:min-h-0 dark:text-rose-400">
                削除
            </button>

            <x-confirm-dialog name="master-delete-{{ $record->id }}"
                              title="{{ $resourceLabel }}を削除しますか？"
                              :action="route($routeName.'.destroy', $record->id)"
                              method="DELETE"
                              confirm="削除する">
                論理削除のためデータは残ります（管理者は削除済みの表示・復元ができます）。
            </x-confirm-dialog>
        @endif
    @endcan
</td>
