<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    商談一覧
                </h2>

                @include('crm.deals._view-switch')
            </div>

            @can(\App\Enums\PermissionName::MasterManage->value)
                <a href="{{ route('deals.create') }}"
                   class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-primary-hover">
                    商談を追加
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash />

            @include('crm.deals._summary')

            <x-data-table :table="$table">
                <x-slot name="extraFilters">
                    @include('crm.deals._filters')
                </x-slot>

                @foreach ($table->items() as $deal)
                    <x-table.row :muted="$deal->trashed()">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">
                            <a href="{{ route('deals.show', $deal->id) }}"
                               class="text-primary-text hover:text-primary-hover hover:underline">
                                {{ $deal->code }}
                            </a>
                        </td>

                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                            @if ($deal->partner)
                                <a href="{{ route('customers.show', $deal->partner_id) }}" class="hover:underline">
                                    {{ $deal->partner->name }}
                                </a>
                            @else
                                &mdash;
                            @endif
                        </td>

                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('deals.show', $deal->id) }}" class="hover:underline">{{ $deal->title }}</a>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $deal->status->badgeClass() }}">
                                {{ $deal->status->label() }}
                            </span>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                            {{ $deal->probability }}%
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">
                            {{ number_format($deal->amount_total) }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ $deal->expected_close_date->format('Y/m/d') }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ $deal->ordered_at?->format('Y/m/d') ?? '—' }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ $deal->employee?->name ?? '—' }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($deal->trashed())
                                @if (auth()->user()?->isAdmin())
                                    <form method="POST" action="{{ route('deals.restore', $deal->id) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="text-xs font-medium text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                                                onclick="return confirm('この商談を復元しますか?')">
                                            復元
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">&mdash;</span>
                                @endif
                            @else
                                <a href="{{ route('deals.show', $deal->id) }}"
                                   class="text-xs font-medium text-primary-text hover:text-primary-hover">
                                    詳細
                                </a>

                                @can(\App\Enums\PermissionName::MasterManage->value)
                                    <a href="{{ route('deals.edit', $deal->id) }}"
                                       class="ms-3 text-xs font-medium text-primary-text hover:text-primary-hover">
                                        編集
                                    </a>
                                @endcan
                            @endif
                        </td>
                    </x-table.row>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>
