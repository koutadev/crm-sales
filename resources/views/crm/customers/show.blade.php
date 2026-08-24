@php
    use App\Enums\PermissionName;

    /** @var \App\Models\Partner $customer */
    // 担当者フォームで入力エラーが起きたときは、担当者タブを開いた状態で戻す
    $initialTab = $errors->any() ? 'contacts' : $tab;

    $tabs = [
        'overview' => '概要',
        'contacts' => '担当者（'.$contacts->count().'）',
        'deals' => '商談（'.$deals->count().'）',
        'activities' => '活動（'.$activities->count().'）',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $customer->name }}
                </h2>
                <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $customer->code }}</span>
                <x-active-badge :active="$customer->is_active" :trashed="$customer->trashed()" />
            </div>

            <a href="{{ route('customers.index') }}"
               class="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                顧客一覧へ戻る
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ tab: '{{ $initialTab }}' }">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash />

            {{-- タブ --}}
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex flex-wrap gap-6" aria-label="顧客詳細のタブ">
                    @foreach ($tabs as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}'
                                    ? 'border-primary text-gray-900 dark:text-gray-100'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div x-show="tab === 'overview'" x-cloak>
                @include('crm.customers.partials.overview')
            </div>

            <div x-show="tab === 'contacts'" x-cloak>
                @include('crm.customers.partials.contacts')
            </div>

            <div x-show="tab === 'deals'" x-cloak>
                @include('crm.customers.partials.deals')
            </div>

            <div x-show="tab === 'activities'" x-cloak>
                @include('crm.customers.partials.activities')
            </div>
        </div>
    </div>
</x-app-layout>
