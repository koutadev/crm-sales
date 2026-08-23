<?php

namespace App\Http\Requests\Masters;

/**
 * 「名称 + 有効フラグ」だけのサブマスタ(部署 / 役職 / 商品分類)用。
 *
 * コードは自動採番のため入力を受け付けない。
 */
class SimpleMasterRequest extends MasterRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
