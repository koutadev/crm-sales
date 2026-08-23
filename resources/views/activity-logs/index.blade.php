<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Activity Log') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        誰がいつ何を操作したかの記録です。作成・更新・削除・ログインが自動で記録されます。
                    </p>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th scope="col" class="py-3 pe-4 font-medium">日時</th>
                                    <th scope="col" class="py-3 pe-4 font-medium">操作者</th>
                                    <th scope="col" class="py-3 pe-4 font-medium">操作</th>
                                    <th scope="col" class="py-3 pe-4 font-medium">対象</th>
                                    <th scope="col" class="py-3 pe-4 font-medium">変更内容</th>
                                    <th scope="col" class="py-3 font-medium">IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($logs as $log)
                                    <tr>
                                        <td class="py-3 pe-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            {{ $log->created_at?->format('Y/m/d H:i:s') }}
                                        </td>
                                        <td class="py-3 pe-4 whitespace-nowrap">
                                            {{ $log->user?->name ?? 'システム' }}
                                        </td>
                                        <td class="py-3 pe-4 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                {{ $log->actionLabel() }}
                                            </span>
                                        </td>
                                        <td class="py-3 pe-4">
                                            @if ($log->subject_type)
                                                <span class="font-medium">{{ class_basename($log->subject_type) }}</span>
                                                <span class="text-gray-500 dark:text-gray-400">
                                                    #{{ $log->subject_id }}{{ $log->subject_label ? ' '.$log->subject_label : '' }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="py-3 pe-4">
                                            @if ($log->changes)
                                                {{-- 変更内容は Alpine.js で開閉する --}}
                                                <div x-data="{ open: false }">
                                                    <button type="button" x-on:click="open = ! open"
                                                            class="text-primary-text hover:text-primary-hover text-xs">
                                                        <span x-show="! open">{{ count($log->changes) }} 項目を表示</span>
                                                        <span x-show="open" x-cloak>閉じる</span>
                                                    </button>
                                                    <dl x-show="open" x-cloak class="mt-2 space-y-1">
                                                        @foreach ($log->changes as $key => $value)
                                                            <div class="flex gap-2 text-xs">
                                                                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                                                                <dd class="break-all">{{ is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                </div>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            {{ $log->ip_address ?? '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                            操作ログはまだ記録されていません。
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
