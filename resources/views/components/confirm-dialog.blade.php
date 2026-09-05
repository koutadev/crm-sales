@props([
    'name',
    'title' => '確認',
    'action' => null,
    'method' => 'POST',
    'confirm' => '実行する',
    'cancel' => 'キャンセル',
    'variant' => 'danger',
    // 実行フォームに一緒に送る値（例: 戻り先）
    'fields' => [],
])

{{--
    確認ダイアログ(確認メッセージ + 実行 / キャンセル)。

        <x-button type="button" variant="danger"
                  x-on:click="$dispatch('open-modal', 'delete-employee')">削除</x-button>

        <x-confirm-dialog name="delete-employee" title="社員を削除しますか？"
                          :action="route('masters.employees.destroy', $employee->id)"
                          method="DELETE" confirm="削除する">
            論理削除のためデータは残ります。
        </x-confirm-dialog>

    誤操作を防ぐため、オーバーレイのクリックと Esc では閉じない。
--}}
<x-modal :name="$name" :title="$title" size="sm" :closable="false">
    <p>{{ $slot }}</p>

    <x-slot name="footer">
        <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">{{ $cancel }}</x-button>

        @if ($action !== null)
            <form method="POST" action="{{ $action }}" class="inline">
                @csrf
                @if (strtoupper($method) !== 'POST')
                    @method($method)
                @endif

                @foreach ($fields as $field => $value)
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endforeach

                <x-button type="submit" :variant="$variant">{{ $confirm }}</x-button>
            </form>
        @else
            <x-button type="button" :variant="$variant" x-on:click="$dispatch('confirmed', { name: '{{ $name }}' }); $dispatch('close')">
                {{ $confirm }}
            </x-button>
        @endif
    </x-slot>
</x-modal>
