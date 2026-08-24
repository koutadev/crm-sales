<?php

namespace App\Http\Requests\Masters;

use App\Enums\EmploymentStatus;
use Illuminate\Validation\Rule;

class EmployeeRequest extends MasterRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $id = $this->recordId();

        return [
            'name' => ['required', 'string', 'max:100'],

            // bail + integer は、数値でない値のまま exists へ渡して
            // PostgreSQL の型エラー(500)になるのを防ぐため
            'department_id' => ['bail', 'nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'position_id' => ['bail', 'nullable', 'integer', Rule::exists('positions', 'id')->whereNull('deleted_at')],

            // 論理削除されたレコードのメールは再利用できるようにする
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::unique('employees', 'email')->ignore($id)->whereNull('deleted_at'),
            ],

            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],

            'user_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('employees', 'user_id')->ignore($id)->whereNull('deleted_at'),
            ],

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
            'department_id' => '部署',
            'position_id' => '役職',
            'email' => 'メールアドレス',
            'employment_status' => '在籍状態',
            'user_id' => 'ログインユーザー',
            'is_active' => '状態',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'このログインユーザーは既に別の社員に紐付いています。',
        ];
    }
}
