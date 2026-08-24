<?php

namespace App\Http\Requests\Crm;

use App\Enums\DealStatus;
use App\Http\Requests\Masters\MasterRequest;
use Illuminate\Validation\Rule;

/**
 * 商談の登録 / 編集。
 *
 * 権限チェックはルート側(master.manage)で行う。
 * 金額はここでは受け取らない(明細から必ずサーバ側で計算する)。
 */
class DealRequest extends MasterRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        // 受注にするには「受注日」と「明細 1 件以上」が要る
        $isWon = $this->input('status') === DealStatus::Won->value;

        return [
            'partner_id' => ['required', Rule::exists('partners', 'id')->whereNull('deleted_at')],
            'partner_contact_id' => [
                'nullable',
                Rule::exists('partner_contacts', 'id')
                    ->whereNull('deleted_at')
                    ->where('partner_id', $this->input('partner_id')),
            ],
            'employee_id' => ['required', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:191'],
            'status' => ['required', Rule::enum(DealStatus::class)],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['required', 'date'],
            'ordered_at' => [$isWon ? 'required' : 'nullable', 'date'],

            'items' => $isWon ? ['required', 'array', 'min:1', 'max:100'] : ['nullable', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:99999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => '受注にするには、明細を 1 件以上登録してください。',
            'items.min' => '受注にするには、明細を 1 件以上登録してください。',
            'ordered_at.required' => 'ステータスが「受注」のときは受注日が必要です。',
            'partner_contact_id.exists' => '選択した顧客に紐づく担当者を選んでください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'partner_id' => '顧客',
            'partner_contact_id' => '先方担当',
            'employee_id' => '営業担当',
            'title' => '件名',
            'status' => 'ステータス',
            'probability' => '確度',
            'expected_close_date' => '予定クローズ日',
            'ordered_at' => '受注日',
            'items' => '明細',
            'items.*.product_id' => '商品',
            'items.*.quantity' => '数量',
            'items.*.unit_price' => '税込単価',
        ];
    }
}
