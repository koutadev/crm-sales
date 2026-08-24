<?php

namespace App\Http\Requests\Masters;

use Illuminate\Validation\Rule;

class ProductRequest extends MasterRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'product_category_id' => ['nullable', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'unit_price' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'unit' => ['nullable', 'string', 'max:16'],
            'tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->whereNull('deleted_at')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '商品名',
            'product_category_id' => '分類',
            'unit_price' => '標準単価(税込)',
            'unit' => '単位',
            'tax_rate_id' => '税率',
            'is_active' => '状態',
        ];
    }
}
