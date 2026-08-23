@props(['record'])

{{-- 有効フラグ。新規登録時は既定で有効にする --}}
<label class="inline-flex items-center gap-2">
    <input type="checkbox" name="is_active" value="1"
           @checked(old('is_active', $record->exists ? $record->is_active : true))
           class="rounded border-gray-300 text-primary-text shadow-sm focus:ring-primary dark:border-gray-700 dark:bg-gray-900">
    <span class="text-sm text-gray-700 dark:text-gray-300">有効</span>
</label>

<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
    無効にすると、今後の選択肢には出なくなりますが、過去データからは参照できます。
</p>

<x-input-error :messages="$errors->get('is_active')" class="mt-1" />
