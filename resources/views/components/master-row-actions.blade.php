@props([
    'record',
    'routeName',
    'resourceLabel',
])

{{-- 一覧の「操作」列。権限と削除状態に応じて出し分ける --}}
<td class="whitespace-nowrap px-4 py-3 text-right">
    @can(\App\Enums\PermissionName::MasterManage->value)
        @if ($record->trashed())
            {{-- 復元は管理者のみ --}}
            @if (auth()->user()?->isAdmin())
                <form method="POST" action="{{ route($routeName.'.restore', $record->id) }}" class="inline">
                    @csrf
                    <button type="submit"
                            class="text-xs font-medium text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                            onclick="return confirm('この{{ $resourceLabel }}を復元しますか?')">
                        復元
                    </button>
                </form>
            @else
                <span class="text-xs text-gray-400">&mdash;</span>
            @endif
        @else
            <a href="{{ route($routeName.'.edit', $record->id) }}"
               class="text-xs font-medium text-primary-text hover:text-primary-hover">
                編集
            </a>

            <form method="POST" action="{{ route($routeName.'.destroy', $record->id) }}" class="ms-3 inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="text-xs font-medium text-rose-600 hover:text-rose-500 dark:text-rose-400"
                        onclick="return confirm('この{{ $resourceLabel }}を削除しますか?（論理削除のためデータは残ります）')">
                    削除
                </button>
            </form>
        @endif
    @else
        <span class="text-xs text-gray-400">&mdash;</span>
    @endcan
</td>
