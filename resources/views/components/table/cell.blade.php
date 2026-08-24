@props([
    'align' => 'left',
    'wrap' => true,
    'muted' => false,
    'mono' => false,
    'strong' => false,
])

{{-- 一覧のセル。整列・折り返し・淡色などをそろえる。 --}}
<td @class([
    'px-4 py-3',
    'text-left' => $align === 'left',
    'text-right tabular-nums' => $align === 'right',
    'text-center' => $align === 'center',
    'whitespace-nowrap' => ! $wrap,
    'text-gray-500 dark:text-gray-400' => $muted,
    'font-mono text-xs' => $mono,
    'font-medium' => $strong,
]) {{ $attributes }}>
    {{ $slot }}
</td>
