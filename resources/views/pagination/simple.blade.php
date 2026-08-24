{{-- 前へ / 次へ だけの簡易ページネーション(simplePaginate 用)。 --}}
@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-2" aria-label="ページ送り">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center rounded-md border border-gray-200 px-3 py-1.5 text-sm text-gray-300 dark:border-gray-700 dark:text-gray-600"
                  aria-disabled="true">前へ</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 transition hover:bg-gray-50 motion-reduce:transition-none dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">前へ</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 transition hover:bg-gray-50 motion-reduce:transition-none dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">次へ</a>
        @else
            <span class="inline-flex items-center rounded-md border border-gray-200 px-3 py-1.5 text-sm text-gray-300 dark:border-gray-700 dark:text-gray-600"
                  aria-disabled="true">次へ</span>
        @endif
    </nav>
@endif
