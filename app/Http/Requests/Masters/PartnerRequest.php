<?php

namespace App\Http\Requests\Masters;

use App\Enums\EntityType;
use App\Enums\PartnerType;
use Illuminate\Validation\Rule;

class PartnerRequest extends MasterRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'partner_type' => ['required', Rule::enum(PartnerType::class)],
            'entity_type' => ['required', Rule::enum(EntityType::class)],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '取引先名',
            'partner_type' => '取引先区分',
            'entity_type' => '法人/個人',
            'email' => 'メールアドレス',
            'phone' => '電話番号',
            'postal_code' => '郵便番号',
            'address' => '住所',
            'is_active' => '状態',
        ];
    }
}
