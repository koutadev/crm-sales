<x-master-index :table="$table" :resource-label="$resourceLabel" :route-name="$routeName">
    @foreach ($table->items() as $taxRate)
        <tr class="{{ $taxRate->trashed() ? 'opacity-60' : '' }}">
            <td class="px-4 py-3 font-medium">{{ $taxRate->name }}</td>
            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $taxRate->rate_percent }}%</td>
            <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                {{ $taxRate->effective_from->format('Y/m/d') }}〜
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-center">
                <x-active-badge :active="$taxRate->is_active" :trashed="$taxRate->trashed()" />
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                {{ $taxRate->updated_at?->format('Y/m/d H:i') }}
            </td>

            <x-master-row-actions :record="$taxRate" :route-name="$routeName" :resource-label="$resourceLabel" />
        </tr>
    @endforeach
</x-master-index>
