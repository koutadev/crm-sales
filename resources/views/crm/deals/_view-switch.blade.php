{{-- 表 / カンバンの切り替え。いまの絞り込み条件はそのまま引き継ぐ --}}
@php
    $query = $table->state->toQuery();
@endphp

<div class="inline-flex rounded-md border border-gray-300 p-0.5 dark:border-gray-700" role="tablist" aria-label="表示形式">
    @foreach (\App\Tables\DealTable::VIEW_MODES as $mode => $label)
        <a href="{{ route('deals.index', array_merge($query, ['view_mode' => $mode])) }}"
           role="tab"
           aria-selected="{{ $viewMode === $mode ? 'true' : 'false' }}"
           @class([
               'rounded px-3 py-1.5 text-xs font-medium transition-colors motion-reduce:transition-none',
               'bg-primary text-white' => $viewMode === $mode,
               'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' => $viewMode !== $mode,
           ])>
            {{ $label }}
        </a>
    @endforeach
</div>
