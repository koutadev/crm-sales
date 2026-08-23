@props([
    'table',
    'resourceLabel',
    'routeName',
])

{{--
    マスタ一覧画面の共通枠。
    各マスタの index ビューは、この中に <tr> だけを書けばよい。
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $resourceLabel }}マスタ
            </h2>

            @can(\App\Enums\PermissionName::MasterManage->value)
                <a href="{{ route($routeName.'.create') }}"
                   class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-primary-hover">
                    {{ $resourceLabel }}を新規登録
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-flash />

            <x-data-table :table="$table">
                {{ $slot }}
            </x-data-table>
        </div>
    </div>
</x-app-layout>
