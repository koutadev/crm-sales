<x-master-index :table="$table" :resource-label="$resourceLabel" :route-name="$routeName" :initial-detail="$initialDetail ?? null">
    @foreach ($table->items() as $employee)
        <x-table.row :muted="$employee->trashed()"
                     :detail-url="route($routeName.'.detail', $employee->id)">
            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $employee->code }}</td>
            <td class="px-4 py-3 font-medium">{{ $employee->name }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $employee->department?->name ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $employee->position?->name ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $employee->email ?? '—' }}</td>
            <td class="whitespace-nowrap px-4 py-3 text-center">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $employee->employment_status->badgeClass() }}">
                    {{ $employee->employment_status->label() }}
                </span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-center">
                <x-active-badge :active="$employee->is_active" :trashed="$employee->trashed()" />
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                {{ $employee->updated_at?->format('Y/m/d H:i') }}
            </td>

            <x-master-row-actions :record="$employee" :route-name="$routeName" :resource-label="$resourceLabel" />
        </x-table.row>
    @endforeach
</x-master-index>
