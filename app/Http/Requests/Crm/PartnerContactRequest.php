<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\Masters\MasterRequest;

/**
 * 顧客詳細の「担当者」タブから送られる、取引先担当者の登録 / 編集。
 *
 * 権限チェックはルート側(master.manage)で行う。
 */
class PartnerContactRequest extends MasterRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'department' => '部署',
            'position' => '役職',
            'email' => 'メールアドレス',
            'phone' => '電話番号',
            'is_active' => '状態',
        ];
    }
}
