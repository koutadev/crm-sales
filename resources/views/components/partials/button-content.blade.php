{{-- ボタンの中身(リンク版・ボタン版で共有) --}}
@if ($loading)
    <svg class="animate-spin {{ $resolvedSize->iconClasses() }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
    <span class="sr-only">処理中</span>
@elseif ($icon !== null)
    <x-icon :name="$icon" :class="$resolvedSize->iconClasses()" />
@endif

{{ $slot }}
