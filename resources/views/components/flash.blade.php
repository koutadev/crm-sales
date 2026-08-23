@if (session('status'))
    <div x-data="{ show: true }" x-show="show" x-cloak
         class="mb-4 flex items-start justify-between gap-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
        <span>{{ session('status') }}</span>
        <button type="button" x-on:click="show = false" class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-300">
            &times;<span class="sr-only">閉じる</span>
        </button>
    </div>
@endif

@if ($errors->any() && $errors->has('roles'))
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/40 dark:text-rose-200">
        {{ $errors->first('roles') }}
    </div>
@endif
