<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 保存ビュー(マイビュー)の保存。
 */
class SavedViewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'table_key' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
            'conditions' => ['nullable', 'array'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ビュー名',
        ];
    }
}
