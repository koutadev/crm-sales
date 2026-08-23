<x-master-form :record="$taxRate" :resource-label="$resourceLabel" :route-name="$routeName">
    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
        税率が変わったときは、既存のレコードを書き換えず<strong>適用開始日の新しいレコードを追加</strong>してください。
        確定済みの商談明細は当時の税率をコピー保持するため、世代を追加しても過去の金額は変わりません。
    </div>

    <x-form-field name="name" label="税率名" :required="true" help="標準 / 軽減 など。同じ名称で適用開始日の違うレコードを追加すると、税率の世代になります。">
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      :value="old('name', $taxRate->name)" required autofocus />
    </x-form-field>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <x-form-field name="rate_percent" label="税率(%)" :required="true" help="0〜100 の整数で入力します。">
            <x-text-input id="rate_percent" name="rate_percent" type="number" step="1" min="0" max="100"
                          class="mt-1 block w-full text-right"
                          :value="old('rate_percent', $taxRate->exists ? $taxRate->rate_percent : '10')" required />
        </x-form-field>

        <x-form-field name="effective_from" label="適用開始日" :required="true" help="この日から適用される税率です。">
            <x-text-input id="effective_from" name="effective_from" type="date" class="mt-1 block w-full"
                          :value="old('effective_from', $taxRate->effective_from?->format('Y-m-d'))" required />
        </x-form-field>
    </div>

    <div>
        <x-active-checkbox :record="$taxRate" />
    </div>
</x-master-form>
