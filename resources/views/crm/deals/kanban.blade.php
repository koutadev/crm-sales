@php
    /** @var \App\Support\Crm\DealKanban $kanban */
    $canManage = auth()->user()?->can(\App\Enums\PermissionName::MasterManage->value) ?? false;
@endphp

<x-app-layout>
    <x-slot name="breadcrumb">カンバン</x-slot>

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

            {{-- 絞り込みは一覧とまったく同じ(条件も保存ビューも共通) --}}
            <x-table-filters :table="$table">
                <x-slot name="extraFilters">
                    @include('crm.deals._filters')
                </x-slot>
            </x-table-filters>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    全 {{ number_format($summary->dealCount) }} 件
                    <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">
                        （各列 {{ \App\Support\Crm\DealKanban::LANE_LIMIT }} 件まで表示、予定クローズ日が近い順）
                    </span>
                </p>

                @if ($table->definition->exportable())
                    <a href="{{ $table->exportUrl() }}"
                       class="text-sm text-primary-text underline hover:text-primary-hover">CSV 出力</a>
                @endif
            </div>

            {{-- カンバン本体 --}}
            <div x-data="dealKanban({{ $canManage ? 'true' : 'false' }})"
                 class="overflow-x-auto pb-4">
                <div class="flex min-w-max gap-4">
                    @foreach ($kanban->lanes as $lane)
                        <section class="w-72 shrink-0 rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40"
                                 data-status="{{ $lane->status->value }}"
                                 @if ($canManage)
                                     x-on:dragover.prevent="over = '{{ $lane->status->value }}'"
                                     x-on:dragleave="over = null"
                                     x-on:drop.prevent="drop($el, '{{ $lane->status->value }}')"
                                 @endif
                                 :class="over === '{{ $lane->status->value }}' ? 'ring-2 ring-primary' : ''">

                            {{-- 列ヘッダー(件数と税込金額は絞り込み後の全件ぶん) --}}
                            <header class="sticky top-0 rounded-t-lg border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $lane->status->badgeClass() }}">
                                        {{ $lane->status->label() }}
                                    </span>
                                    <span class="text-xs font-medium tabular-nums text-gray-600 dark:text-gray-400">
                                        {{ number_format($lane->count) }} 件
                                    </span>
                                </div>

                                <p class="mt-1 text-right text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                    {{ number_format($lane->amountInclTax) }}
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">円</span>
                                </p>
                            </header>

                            <div class="space-y-2 p-2" data-cards>
                                @forelse ($lane->deals as $deal)
                                    <article class="rounded-md border border-gray-200 bg-white p-3 shadow-sm transition hover:border-primary hover:shadow motion-reduce:transition-none dark:border-gray-700 dark:bg-gray-800"
                                             data-deal-id="{{ $deal->id }}"
                                             @if ($canManage)
                                                 draggable="true"
                                                 x-on:dragstart="start($event, {{ $deal->id }}, '{{ $lane->status->value }}')"
                                                 x-on:dragend="over = null"
                                             @endif>
                                        <a href="{{ route('deals.show', $deal->id) }}" class="block">
                                            <p class="font-mono text-[11px] text-gray-400 dark:text-gray-500">{{ $deal->code }}</p>
                                            <p class="mt-0.5 line-clamp-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $deal->title }}</p>
                                            <p class="mt-1 truncate text-xs text-gray-600 dark:text-gray-400">{{ $deal->partner?->name ?? '—' }}</p>

                                            <p class="mt-2 text-right text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                                {{ number_format($deal->amount_total) }}
                                                <span class="text-[11px] font-normal text-gray-500 dark:text-gray-400">円</span>
                                            </p>

                                            <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                                <span class="tabular-nums">確度 {{ $deal->probability }}%</span>
                                                <span class="tabular-nums">{{ $deal->expected_close_date->format('Y/m/d') }}</span>
                                            </div>

                                            <p class="mt-1 truncate text-[11px] text-gray-500 dark:text-gray-400">
                                                {{ $deal->employee?->name ?? '担当未設定' }}
                                            </p>
                                        </a>
                                    </article>
                                @empty
                                    <p class="px-2 py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                        商談はありません
                                    </p>
                                @endforelse

                                @if ($lane->hiddenCount() > 0)
                                    <p class="px-2 py-2 text-center text-xs text-gray-500 dark:text-gray-400">
                                        他 {{ number_format($lane->hiddenCount()) }} 件（一覧で確認してください）
                                    </p>
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>

            @if ($canManage)
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    カードは列をまたいでドラッグできます（ステータスが変わります）。
                    「受注」へ移すと受注日に今日が入り、そこから戻すと受注日は消えます。明細のない商談は受注にできません。
                </p>
            @endif
        </div>
    </div>

    @push('head')
        <script>
            // カンバンのドラッグ&ドロップ。失敗したらカードを元の列に戻す。
            function dealKanban(canManage) {
                return {
                    canManage,
                    over: null,
                    dragging: null,

                    start(event, dealId, from) {
                        if (! this.canManage) {
                            return;
                        }

                        this.dragging = { dealId, from, card: event.target.closest('[data-deal-id]') };
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', String(dealId));
                    },

                    async drop(lane, status) {
                        this.over = null;

                        const moving = this.dragging;
                        this.dragging = null;

                        if (! moving || moving.from === status) {
                            return;
                        }

                        const container = lane.querySelector('[data-cards]') ?? lane.lastElementChild;
                        const origin = moving.card.parentElement;

                        // まず動かして、失敗したら戻す
                        container.prepend(moving.card);

                        try {
                            const endpoint = '{{ route('deals.status.update', ['id' => '__ID__']) }}'.replace('__ID__', moving.dealId);

                            const response = await fetch(endpoint, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                },
                                body: JSON.stringify({ status }),
                            });

                            const payload = await response.json().catch(() => ({}));

                            if (! response.ok) {
                                throw new Error(payload.message ?? 'ステータスを変更できませんでした。');
                            }

                            window.toast(payload.message ?? 'ステータスを変更しました。', 'success');

                            // 列ヘッダーの件数・金額を正しく出し直すため、条件はそのままで読み直す
                            window.location.reload();
                        } catch (error) {
                            origin.prepend(moving.card);
                            window.toast(error.message, 'danger');
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
