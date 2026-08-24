@props(['paginator'])

{{-- ページネーション。中身は resources/views/pagination/app.blade.php。 --}}
@if ($paginator->hasPages())
    <div {{ $attributes }}>
        {{ $paginator->onEachSide(1)->links() }}
    </div>
@endif
