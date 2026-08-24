@php
    use App\Support\Ui\Toast;
    use App\Support\Ui\Tone;

    // 色は Tailwind のクラスを静的に持たせる必要があるため、PHP 側で組み立てて JS に渡す
    $toneClasses = [];

    foreach (Tone::cases() as $tone) {
        $toneClasses[$tone->value] = $tone->alertClasses();
    }

    $flashed = session(Toast::SESSION_KEY);
@endphp

{{--
    トースト通知の表示場所。レイアウトに 1 つ置く。

    - リダイレクト時: ->with('toast', Toast::success('保存しました'))
    - 画面から     : $dispatch('toast', { type: 'error', message: '…' })
--}}
<div x-data="{ classes: @js($toneClasses) }"
     @if ($flashed) x-init="$store.toast.push(@js($flashed))" @endif
     class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
     aria-live="polite"
     aria-atomic="true">
    <template x-for="item in $store.toast.items" :key="item.id">
        <div x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
             x-transition:leave-end="opacity-0"
             :class="classes[item.type] ?? classes.neutral"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
             role="status">
            <span class="flex-1" x-text="item.message"></span>

            <button type="button" @click="$store.toast.remove(item.id)"
                    class="shrink-0 opacity-60 transition hover:opacity-100 motion-reduce:transition-none"
                    aria-label="閉じる">
                <x-icon name="close" class="h-4 w-4" />
            </button>
        </div>
    </template>
</div>
