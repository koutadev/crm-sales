@props(['active' => true, 'trashed' => false])

@if ($trashed)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800 dark:bg-rose-900 dark:text-rose-200']) }}>
        削除済み
    </span>
@elseif ($active)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200']) }}>
        有効
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300']) }}>
        無効
    </span>
@endif
