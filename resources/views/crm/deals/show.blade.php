@php
    use App\Enums\PermissionName;

    /** @var \App\Models\Deal $deal */
    /** @var \App\Support\Crm\AmountSummary $summary */
    $canManage = auth()->user()?->can(PermissionName::MasterManage->value) ?? false;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
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

            <div class="flex items-center gap-4">
                @if ($canManage && ! $deal->trashed())
                    <a href="{{ route('deals.edit', $deal->id) }}"
                       class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-primary-hover">
                        編集
                    </a>
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

            @include('crm.deals.partials.info')
            @include('crm.deals.partials.items')
            @include('crm.deals.partials.activities')
        </div>
    </div>
</x-app-layout>
