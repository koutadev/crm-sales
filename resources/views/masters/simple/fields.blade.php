{{-- サブマスタ(部署 / 役職 / 商品分類)の入力項目。フルページのフォームとモーダル編集で共有する。 --}}
<x-form-field name="name" :label="$nameLabel" :required="true">
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $record->name)" required autofocus />
</x-form-field>

<div>
    <x-active-checkbox :record="$record" />
</div>
