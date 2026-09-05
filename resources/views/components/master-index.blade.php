@props([
    'table',
    'resourceLabel',
    'routeName',
    'initialDetail' => null,
    // 登録・編集に必要な権限。マスタ単位に権限を分ける場合だけ渡す
    'managePermission' => \App\Enums\PermissionName::MasterManage->value,
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

            <div class="flex flex-wrap items-center gap-3">
                {{-- マスタ固有のボタン(一括複製など)を足したいときはこのスロットへ --}}
                @isset($headerActions)
                    {{ $headerActions }}
                @endisset

                @can($managePermission)
                    <a href="{{ route($routeName.'.create') }}"
                       class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-primary-hover">
                        {{ $resourceLabel }}を新規登録
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-flash />

            <x-data-table :table="$table">
                {{ $slot }}
            </x-data-table>

            {{-- 行クリックで開く詳細・編集モーダルと、削除の確認ダイアログ --}}
            <x-master-detail-modal :initial-detail="$initialDetail" :resource-label="$resourceLabel" />
        </div>
    </div>
</x-app-layout>
