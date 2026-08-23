<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            ユーザー管理 &mdash; ロールの変更
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <x-flash />

            <form method="POST" action="{{ route('users.update', $user->id) }}"
                  class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                @csrf
                @method('PUT')

                <div>
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">対象ユーザー</span>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $user->name }}（{{ $user->email }}）
                    </p>
                </div>

                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">ロール</legend>

                    @foreach ($roleOptions as $value => $label)
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="roles[]" value="{{ $value }}"
                                   @checked(in_array($value, old('roles', $currentRoles), true))
                                   class="mt-0.5 rounded border-gray-300 text-primary-text shadow-sm focus:ring-primary dark:border-gray-700 dark:bg-gray-900">
                            <span>
                                <span class="text-sm text-gray-800 dark:text-gray-200">{{ $label }}</span>
                                <span class="ms-1 font-mono text-xs text-gray-400">{{ $value }}</span>
                            </span>
                        </label>
                    @endforeach

                    <x-input-error :messages="$errors->get('roles')" class="mt-1" />
                </fieldset>

                <div class="flex items-center gap-3 border-t border-gray-100 pt-6 dark:border-gray-700">
                    <x-primary-button type="submit">保存</x-primary-button>

                    <a href="{{ route('users.index') }}"
                       class="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                        キャンセル
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
