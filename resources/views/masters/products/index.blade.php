<x-master-index :table="$table" :resource-label="$resourceLabel" :route-name="$routeName" :initial-detail="$initialDetail ?? null">
    @foreach ($table->items() as $product)
        <x-table.row :muted="$product->trashed()"
                     :detail-url="route($routeName.'.detail', $product->id)">
            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $product->code }}</td>
            <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $product->category?->name ?? '—' }}</td>
            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                {{ number_format($product->unit_price) }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                @if ($product->taxRate)
                    <span class="tabular-nums">{{ $product->taxRate->rate_percent }}%</span>
                    <span class="ms-1 text-xs text-gray-500 dark:text-gray-500">{{ $product->taxRate->name }}</span>
                @else
                    —
                @endif
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                {{ $product->unit ?? '—' }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-center">
                <x-active-badge :active="$product->is_active" :trashed="$product->trashed()" />
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                {{ $product->updated_at?->format('Y/m/d H:i') }}
            </td>

            <x-master-row-actions :record="$product" :route-name="$routeName" :resource-label="$resourceLabel" />
        </x-table.row>
    @endforeach
</x-master-index>
