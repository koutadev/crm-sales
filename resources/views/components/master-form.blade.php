@props([
    'record',
    'routeName',
    'resourceLabel',
])

@php
    $editing = $record->exists;
    $action = $editing ? route($routeName.'.update', $record->id) : route($routeName.'.store');
@endphp

{{--
    マスタ登録 / 編集画面の共通枠。
    各マスタの form ビューは入力項目だけを書けばよい。
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ $resourceLabel }}マスタ &mdash; {{ $editing ? '編集' : '新規登録' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ $action }}"
                  class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                @if ($editing && filled($record->code))
                    {{-- コードは自動採番のため変更できない(コードを持たないマスタでは表示しない) --}}
                    <div>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">コード</span>
                        <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $record->code }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">自動採番のため変更できません。</p>
                    </div>
                @endif

                {{ $slot }}

                <div class="flex items-center gap-3 border-t border-gray-100 pt-6 dark:border-gray-700">
                    <x-primary-button type="submit">保存</x-primary-button>

                    <a href="{{ route($routeName.'.index') }}"
                       class="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                        キャンセル
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
