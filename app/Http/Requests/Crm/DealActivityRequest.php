<?php

namespace App\Http\Requests\Crm;

use App\Enums\ActivityType;
use App\Http\Requests\Masters\MasterRequest;
use Illuminate\Validation\Rule;

/**
 * 商談詳細から追加する活動履歴。
 *
 * 権限チェックはルート側(master.manage)で行う。
 */
class DealActivityRequest extends MasterRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['bail', 'required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(ActivityType::class)],
            'activity_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => '実施者',
            'type' => '種別',
            'activity_at' => '実施日時',
            'note' => '内容',
        ];
    }
}
