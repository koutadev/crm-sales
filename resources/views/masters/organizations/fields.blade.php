{{-- 組織の入力項目。フルページのフォームとモーダル編集で共有する。 --}}
<x-form-field name="name" label="組織名" :required="true">
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $organization->name)" required autofocus />
</x-form-field>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <x-form-field name="type" label="種別" :required="true"
                  help="地域 > エリア > 店舗 の 3 段です。社員は店舗に所属します。">
        <x-select-input id="type" name="type" class="mt-1 block w-full"
                        :options="$typeOptions"
                        :selected="old('type', $organization->type?->value ?? \App\Enums\OrganizationType::Store->value)"
                        required />
    </x-form-field>

    <x-form-field name="parent_id" label="上位組織"
                  help="エリアは地域を、店舗はエリアを選びます。地域は空のままにします。">
        <x-form.combobox name="parent_id"
                         :options="$parentOptions"
                         :selected="old('parent_id', $organization->parent_id)"
                         placeholder="組織名で検索" />
    </x-form-field>
</div>

<x-active-checkbox :record="$organization" />
