{{-- 顧客(会社)一覧。金額は商談から集計した税込金額。 --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                顧客一覧
            </h2>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                金額はすべて税込。顧客の追加・編集は
                <a href="{{ route('masters.partners.index') }}" class="underline hover:text-gray-700 dark:hover:text-gray-200">取引先マスタ</a>
                から行います。
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-flash />

            <x-data-table :table="$table">
                @foreach ($table->items() as $customer)
                    <tr class="{{ $customer->trashed() ? 'opacity-60' : '' }}">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $customer->code }}</td>

                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('customers.show', $customer->id) }}"
                               class="text-primary-text hover:text-primary-hover hover:underline">
                                {{ $customer->name }}
                            </a>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                            {{ number_format((int) $customer->won_amount_total) }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                            {{ number_format((int) $customer->open_amount_total) }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                            {{ number_format((int) $customer->deals_count) }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-center">
                            <x-active-badge :active="$customer->is_active" :trashed="$customer->trashed()" />
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                            {{ $customer->updated_at?->format('Y/m/d H:i') }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($customer->trashed())
                                @can(\App\Enums\PermissionName::MasterManage->value)
                                    @if (auth()->user()?->isAdmin())
                                        <form method="POST" action="{{ route('customers.restore', $customer->id) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="text-xs font-medium text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                                                    onclick="return confirm('この顧客を復元しますか?')">
                                                復元
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">&mdash;</span>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">&mdash;</span>
                                @endcan
                            @else
                                <a href="{{ route('customers.show', $customer->id) }}"
                                   class="text-xs font-medium text-primary-text hover:text-primary-hover">
                                    詳細
                                </a>

                                @can(\App\Enums\PermissionName::MasterManage->value)
                                    <form method="POST" action="{{ route('customers.destroy', $customer->id) }}" class="ms-3 inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-xs font-medium text-rose-600 hover:text-rose-500 dark:text-rose-400"
                                                onclick="return confirm('この顧客を削除しますか?（論理削除のためデータは残ります）')">
                                            削除
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>
