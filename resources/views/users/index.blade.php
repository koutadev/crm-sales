<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            ユーザー管理
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-flash />

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                登録済みユーザーのロールを変更できます。ユーザーの作成は本人による新規登録で行います。
            </p>

            <x-data-table :table="$table">
                @foreach ($table->items() as $user)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @forelse ($user->getRoleNames() as $roleName)
                                <span class="me-1 inline-flex items-center rounded-full bg-primary-soft px-2 py-0.5 text-xs font-medium text-primary-soft-fg">
                                    {{ \App\Enums\RoleName::tryFrom($roleName)?->label() ?? $roleName }}
                                </span>
                            @empty
                                <span class="text-xs text-gray-400">ロール未割当</span>
                            @endforelse
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                            {{ $user->created_at?->format('Y/m/d H:i') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('users.edit', $user->id) }}"
                               class="text-xs font-medium text-primary-text hover:text-primary-hover">
                                ロールを変更
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>
