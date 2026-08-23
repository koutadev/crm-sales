{{-- 部署 / 役職 / 商品分類で共有する一覧画面 --}}
<x-master-index :table="$table" :resource-label="$resourceLabel" :route-name="$routeName">
    @foreach ($table->items() as $record)
        <tr class="{{ $record->trashed() ? 'opacity-60' : '' }}">
            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $record->code }}</td>
            <td class="px-4 py-3 font-medium">{{ $record->name }}</td>
            <td class="whitespace-nowrap px-4 py-3 text-center">
                <x-active-badge :active="$record->is_active" :trashed="$record->trashed()" />
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                {{ $record->updated_at?->format('Y/m/d H:i') }}
            </td>

            <x-master-row-actions :record="$record" :route-name="$routeName" :resource-label="$resourceLabel" />
        </tr>
    @endforeach
</x-master-index>
