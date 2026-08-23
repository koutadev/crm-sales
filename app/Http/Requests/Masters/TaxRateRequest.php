<?php

namespace App\Http\Requests\Masters;

class TaxRateRequest extends MasterRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'rate_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '税率名',
            'rate_percent' => '税率(%)',
            'effective_from' => '適用開始日',
            'is_active' => '状態',
        ];
    }
}
