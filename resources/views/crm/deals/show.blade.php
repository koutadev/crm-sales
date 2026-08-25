@php
    use App\Enums\PermissionName;

    /** @var \App\Models\Deal $deal */
    /** @var \App\Support\Crm\AmountSummary $summary */
    $canManage = auth()->user()?->can(PermissionName::MasterManage->value) ?? false;

    // 活動の入力でエラーになって戻ってきたときは、活動タブを開いた状態にする
    $hasActivityErrors = $errors->hasAny(['employee_id', 'type', 'activity_at', 'note']);
@endphp

<x-app-layout>
    <x-slot name="breadcrumb">{{ $deal->code }}</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ $deal->title }}
                    </h2>
                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $deal->code }}</span>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $deal->status->badgeClass() }}">
                        {{ $deal->status->label() }}
                    </span>
                    @if ($deal->trashed())
                        <x-active-badge :active="false" :trashed="true" />
                    @endif
                </div>

                {{-- 誰の案件で、いつ決まる見込みかを見出しの直下に置く --}}
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($deal->partner)
                        <a href="{{ route('customers.show', $deal->partner_id) }}"
                           class="text-primary-text hover:text-primary-hover hover:underline">{{ $deal->partner->name }}</a>
                    @else
                        <span>顧客未設定</span>
                    @endif
                    <span aria-hidden="true">·</span>
                    <span>{{ $deal->employee?->name ?? '担当未設定' }}</span>
                    <span aria-hidden="true">·</span>
                    <span>予定クローズ {{ $deal->expected_close_date->format('Y/m/d') }}</span>
                    @if ($deal->ordered_at)
                        <span aria-hidden="true">·</span>
                        <span>受注 {{ $deal->ordered_at->format('Y/m/d') }}</span>
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-4">
                @if ($canManage && ! $deal->trashed())
                    <x-button :href="route('deals.edit', $deal->id)">編集</x-button>
                @endif

                <a href="{{ route('deals.index') }}"
                   class="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                    商談一覧へ戻る
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash />

            @include('crm.deals.partials.hero')
            @include('crm.deals.partials.next-action')

            <x-tabs sync
                    :active="$hasActivityErrors ? 'activities' : 'overview'"
                    :tabs="[
                        'overview' => '概要',
                        'items' => '明細（'.number_format($deal->items->count()).'）',
                        'activities' => '活動（'.number_format($activities->count()).'）',
                    ]">
                <x-tab-panel name="overview" class="space-y-6">
                    @include('crm.deals.partials.info')
                    @include('crm.deals.partials.amounts')
                </x-tab-panel>

                <x-tab-panel name="items">
                    @include('crm.deals.partials.items')
                </x-tab-panel>

                <x-tab-panel name="activities">
                    @include('crm.deals.partials.activities')
                </x-tab-panel>
            </x-tabs>
        </div>
    </div>
</x-app-layout>
