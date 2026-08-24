@props(['items' => []])

{{-- パンくず。最後の要素が現在地。 --}}
@if ($items !== [])
    <nav {{ $attributes->merge(['class' => 'min-w-0 text-sm']) }} aria-label="パンくず">
        <ol class="flex flex-wrap items-center gap-1 text-gray-500 dark:text-gray-400">
            @foreach ($items as $index => $item)
                <li class="flex min-w-0 items-center gap-1">
                    @if ($index > 0)
                        <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                    @endif

                    @if ($item['url'] !== null)
                        <a href="{{ $item['url'] }}" class="truncate hover:text-gray-700 hover:underline dark:hover:text-gray-200">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span class="truncate font-medium text-gray-700 dark:text-gray-200"
                              @if ($loop->last) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
