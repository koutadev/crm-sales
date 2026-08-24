{{--
    ページネーション。Laravel の既定ビューを差し替えたもの。

    AppServiceProvider で Paginator::defaultView() に登録しているため、
    $items->links() はすべてこの見た目になる。
--}}
@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="ページ送り">
        @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                全 {{ number_format($paginator->total()) }} 件中
                {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }} 件目
            </p>
        @endif

        <ul class="ms-auto flex items-center gap-1">
            {{-- 前のページ --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="inline-flex h-8 items-center rounded-md border border-gray-200 px-2 text-gray-300 dark:border-gray-700 dark:text-gray-600"
                          aria-disabled="true">
                        <x-icon name="chevron-left" class="h-4 w-4" />
                        <span class="sr-only">前のページ</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       class="inline-flex h-8 items-center rounded-md border border-gray-300 px-2 text-gray-600 transition hover:bg-gray-50 motion-reduce:transition-none dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <x-icon name="chevron-left" class="h-4 w-4" />
                        <span class="sr-only">前のページ</span>
                    </a>
                @endif
            </li>

            {{-- ページ番号 --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li>
                        <span class="inline-flex h-8 items-center px-1 text-gray-400 dark:text-gray-500">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page"
                                      class="inline-flex h-8 min-w-8 items-center justify-center rounded-md bg-primary px-2 text-sm font-semibold text-white">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}"
                                   class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-gray-300 px-2 text-sm text-gray-600 transition hover:bg-gray-50 motion-reduce:transition-none dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                    {{ $page }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            {{-- 次のページ --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       class="inline-flex h-8 items-center rounded-md border border-gray-300 px-2 text-gray-600 transition hover:bg-gray-50 motion-reduce:transition-none dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <x-icon name="chevron-right" class="h-4 w-4" />
                        <span class="sr-only">次のページ</span>
                    </a>
                @else
                    <span class="inline-flex h-8 items-center rounded-md border border-gray-200 px-2 text-gray-300 dark:border-gray-700 dark:text-gray-600"
                          aria-disabled="true">
                        <x-icon name="chevron-right" class="h-4 w-4" />
                        <span class="sr-only">次のページ</span>
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
