@php
    use App\Enums\TargetScope;

    $currentScope = old('scope', $target->scope?->value ?? TargetScope::Company->value);
@endphp

{{-- 粒度に応じて対象の候補を差し替える --}}
<div x-data="{
         scope: @js($currentScope),
         options: @js($targetOptions),
         optionsFor() { return this.options[this.scope] ?? {} },
         needsTarget() { return this.scope !== @js(TargetScope::Company->value) },
     }"
     class="space-y-6">

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <x-form-field name="scope" label="粒度" :required="true"
                      help="全社／地域／エリア／店舗／担当者から選びます。">
            <select id="scope" name="scope" required x-model="scope"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                @foreach ($scopeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-form-field>

        <div x-show="needsTarget()" x-cloak>
            <x-form-field name="target_id" label="対象" help="粒度に合わせて候補が変わります。">
                <x-form.combobox name="target_id"
                                 :selected="old('target_id', $target->target_id)"
                                 options-expression="Object.entries(optionsFor()).map(([value, label]) => ({ value, label }))"
                                 placeholder="名前で検索" />
            </x-form-field>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <x-form.select name="year" label="年" :options="$yearOptions"
                       :selected="old('year', $target->year ?? now()->year)" required />

        <x-form.select name="month" label="月"
                       :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => $m.'月'])->all()"
                       :selected="old('month', $target->month ?? now()->month)" required />

        <x-form-field name="amount" label="目標金額(税込)" :required="true" help="円単位の整数で入力します。">
            <x-text-input id="amount" name="amount" type="number" min="0" step="1"
                          class="mt-1 block w-full text-right"
                          :value="old('amount', $target->amount ?? 0)" required />
        </x-form-field>
    </div>

    <x-active-checkbox :record="$target" />
</div>
