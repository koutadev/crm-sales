<x-master-form :record="$product" :resource-label="$resourceLabel" :route-name="$routeName">
    <x-form-field name="name" label="商品名" :required="true">
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      :value="old('name', $product->name)" required autofocus />
    </x-form-field>

    <x-form-field name="product_category_id" label="分類">
        <x-select-input id="product_category_id" name="product_category_id" class="mt-1 block w-full"
                        :options="$categoryOptions"
                        :selected="old('product_category_id', $product->product_category_id)"
                        placeholder="未設定" />
    </x-form-field>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <x-form-field name="unit_price" label="標準単価" :required="true" help="税抜の標準単価を入力します。">
            <x-text-input id="unit_price" name="unit_price" type="number" step="0.01" min="0"
                          class="mt-1 block w-full text-right"
                          :value="old('unit_price', $product->exists ? $product->unit_price : '0')" required />
        </x-form-field>

        <x-form-field name="unit" label="単位" help="個 / 式 / kg など">
            <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full"
                          :value="old('unit', $product->unit)" />
        </x-form-field>
    </div>

    <div>
        <x-active-checkbox :record="$product" />
    </div>
</x-master-form>
