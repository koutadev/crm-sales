@props(['breadcrumbs' => [], 'user' => null])

{{-- 画面上部のバー。パンくずとユーザーメニュー。 --}}
<header class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 bg-white/95 px-4 backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
    <button type="button" @click="openMobile()"
            class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:hidden dark:text-gray-400 dark:hover:bg-gray-700"
            aria-label="メニューを開く"
            aria-controls="app-sidebar"
            :aria-expanded="mobileOpen.toString()">
        <x-icon name="bars" />
    </button>

    <x-breadcrumbs :items="$breadcrumbs" class="hidden sm:block" />

    @if ($user)
        <div class="ms-auto">
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 motion-reduce:transition-none dark:text-gray-300 dark:hover:bg-gray-700">
                        <span class="max-w-32 truncate">{{ $user->name }}</span>
                        <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="border-b border-gray-100 px-4 py-2 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $user->name }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                    </div>

                    <x-dropdown-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    @endif
</header>
