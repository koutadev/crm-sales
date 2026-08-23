<x-master-index :table="$table" :resource-label="$resourceLabel" :route-name="$routeName">
    @foreach ($table->items() as $partner)
        <tr class="{{ $partner->trashed() ? 'opacity-60' : '' }}">
            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $partner->code }}</td>
            <td class="px-4 py-3 font-medium">{{ $partner->name }}</td>
            <td class="whitespace-nowrap px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                {{ $partner->partner_type->label() }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                {{ $partner->entity_type->label() }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">{{ $partner->phone ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $partner->email ?? '—' }}</td>
            <td class="whitespace-nowrap px-4 py-3 text-center">
                <x-active-badge :active="$partner->is_active" :trashed="$partner->trashed()" />
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                {{ $partner->updated_at?->format('Y/m/d H:i') }}
            </td>

            <x-master-row-actions :record="$partner" :route-name="$routeName" :resource-label="$resourceLabel" />
        </tr>
    @endforeach
</x-master-index>
