@props(['sections'])

{{--
    左サイドナビゲーション。

    - 画面幅 lg 以上：常時表示。折りたたみ(アイコンのみ)に切り替えられる
    - lg 未満：既定は隠れていて、上部バーのボタンでオーバーレイ表示
    開閉状態は Alpine の appShell が持つ（折りたたみは localStorage に保存）。
--}}

{{-- モバイル用のオーバーレイ --}}
<div x-show="mobileOpen"
     x-transition.opacity
     x-cloak
     @click="closeMobile()"
     class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
     aria-hidden="true"></div>

{{--
    既定(Alpine 起動前)は「lg 以上で展開表示・lg 未満では画面外」。
    Alpine が動き出したら、開閉に応じて ! 付きのクラスで上書きする。
    こうしておくと、読み込み直後にサイドバーがちらつかない。
--}}
<aside id="app-sidebar"
       :class="[
           mobileOpen ? 'max-lg:translate-x-0!' : '',
           collapsed ? 'lg:w-[4.5rem]!' : '',
       ]"
       class="fixed inset-y-0 start-0 z-50 flex w-64 max-lg:-translate-x-full flex-col border-e border-gray-200 bg-white transition-[transform,width] duration-200 motion-reduce:transition-none lg:w-64 dark:border-gray-700 dark:bg-gray-800">

    {{-- サービス名 --}}
    <div class="flex h-14 shrink-0 items-center gap-2 border-b border-gray-100 px-4 dark:border-gray-700">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2">
            <x-application-logo />
            <span class="truncate text-sm font-semibold text-gray-800 dark:text-gray-200" x-show="! collapsed">
                {{ \App\Support\Theme\Theme::name() }}
            </span>
        </a>

        <button type="button" @click="closeMobile()"
                class="ms-auto rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:hidden dark:hover:bg-gray-700"
                aria-label="メニューを閉じる">
            <x-icon name="close" />
        </button>
    </div>

    {{-- メニュー --}}
    <nav class="flex-1 space-y-4 overflow-y-auto px-2 py-4" aria-label="メインメニュー">
        @foreach ($sections as $section)
            <div class="space-y-1">
                @if ($section->hasHeading())
                    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                       x-show="! collapsed">
                        {{ $section->label }}
                    </p>
                    <hr class="mx-3 border-gray-200 dark:border-gray-700" x-show="collapsed" x-cloak>
                @endif

                @foreach ($section->items as $item)
                    <x-sidebar-link :item="$item" />
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- 折りたたみ切り替え(画面幅 lg 以上) --}}
    <div class="hidden shrink-0 border-t border-gray-100 p-2 lg:block dark:border-gray-700">
        <button type="button" @click="toggleCollapsed()"
                class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 motion-reduce:transition-none dark:text-gray-400 dark:hover:bg-gray-700"
                :aria-expanded="(! collapsed).toString()"
                aria-controls="app-sidebar">
            <x-icon name="chevron-left" class="h-5 w-5 shrink-0 transition-transform motion-reduce:transition-none"
                    ::class="collapsed && 'rotate-180'" />
            <span x-show="! collapsed">メニューを折りたたむ</span>
            <span class="sr-only" x-show="collapsed" x-cloak>メニューを開く</span>
        </button>
    </div>
</aside>
