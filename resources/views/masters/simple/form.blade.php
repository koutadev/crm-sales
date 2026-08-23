{{-- 部署 / 役職 / 商品分類で共有する登録・編集画面 --}}
<x-master-form :record="$record" :resource-label="$resourceLabel" :route-name="$routeName">
    <x-form-field name="name" :label="$nameLabel" :required="true">
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      :value="old('name', $record->name)" required autofocus />
    </x-form-field>

    <div>
        <x-active-checkbox :record="$record" />
    </div>
</x-master-form>
