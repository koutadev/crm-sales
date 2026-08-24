{{-- 取引先の入力項目。フルページのフォームとモーダル編集で共有する。 --}}
<x-form-field name="name" label="取引先名" :required="true">
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $partner->name)" required autofocus />
</x-form-field>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <x-form-field name="partner_type" label="取引先区分" :required="true"
                  help="得意先 / 仕入先 / 両方。受発注・CRM から参照されます。">
        <x-select-input id="partner_type" name="partner_type" class="mt-1 block w-full"
                        :options="$partnerTypeOptions"
                        :selected="old('partner_type', $partner->partner_type?->value ?? \App\Enums\PartnerType::Customer->value)" />
    </x-form-field>

    <x-form-field name="entity_type" label="法人/個人" :required="true">
        <x-select-input id="entity_type" name="entity_type" class="mt-1 block w-full"
                        :options="$entityTypeOptions"
                        :selected="old('entity_type', $partner->entity_type?->value ?? \App\Enums\EntityType::Corporate->value)" />
    </x-form-field>
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <x-form-field name="phone" label="電話番号">
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                      :value="old('phone', $partner->phone)" />
    </x-form-field>

    <x-form-field name="email" label="メールアドレス">
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                      :value="old('email', $partner->email)" />
    </x-form-field>
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
    <x-form-field name="postal_code" label="郵便番号">
        <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full"
                      :value="old('postal_code', $partner->postal_code)" placeholder="100-0001" />
    </x-form-field>

    <x-form-field name="address" label="住所" class="sm:col-span-2">
        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full"
                      :value="old('address', $partner->address)" />
    </x-form-field>
</div>

<div>
    <x-active-checkbox :record="$partner" />
</div>
