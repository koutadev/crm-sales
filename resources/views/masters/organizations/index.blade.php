<x-master-index :table="$table" :resource-label="$resourceLabel" :route-name="$routeName" :initial-detail="$initialDetail ?? null">
    @foreach ($table->items() as $organization)
        <x-table.row :muted="$organization->trashed()"
                     :detail-url="route($routeName.'.detail', $organization->id)">
            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $organization->code }}</td>

            <td class="whitespace-nowrap px-4 py-3 text-center">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $organization->type->badgeClass() }}">
                    {{ $organization->type->label() }}
                </span>
            </td>

            {{-- 階層が分かるよう、深さぶん字下げして名前を出す --}}
            <td class="px-4 py-3 font-medium">
                <span style="padding-inline-start: {{ ($organization->type->depth() - 1) * 1.25 }}rem">
                    @if ($organization->type->depth() > 1)
                        <span class="me-1 text-gray-300 dark:text-gray-600" aria-hidden="true">└</span>
                    @endif
                    {{ $organization->name }}
                </span>
            </td>

            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                {{ $organization->parent?->path() ?? '—' }}
            </td>

            <td class="whitespace-nowrap px-4 py-3 text-center">
                <x-active-badge :active="$organization->is_active" :trashed="$organization->trashed()" />
            </td>

            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                {{ $organization->updated_at?->format('Y/m/d H:i') }}
            </td>

            <x-master-row-actions :record="$organization" :route-name="$routeName" :resource-label="$resourceLabel" />
        </x-table.row>
    @endforeach
</x-master-index>
