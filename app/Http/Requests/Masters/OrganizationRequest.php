<?php

namespace App\Http\Requests\Masters;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Support\Masters\Prefecture;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 組織の登録・編集。
 *
 * 「地域の親は無し／エリアの親は地域／店舗の親はエリア」という段の決まりを、
 * ここでまとめて検証する（3 段より深くはしない）。
 */
class OrganizationRequest extends MasterRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'parent_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('organizations', 'id')->whereNull('deleted_at'),
            ],
            // 都道府県は店舗だけが持つ
            'prefecture' => ['nullable', 'string', Rule::in(Prefecture::names())],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = OrganizationType::tryFrom((string) $this->input('type'));

            if ($type === null) {
                return;
            }

            if ($type !== OrganizationType::Store && $this->filled('prefecture')) {
                $validator->errors()->add('prefecture', '都道府県は店舗にだけ設定できます。');
            }

            $parentId = $this->input('parent_id');
            $expected = $type->parentType();

            if ($expected === null) {
                if ($parentId !== null && $parentId !== '') {
                    $validator->errors()->add('parent_id', '地域は上位組織を持ちません。');
                }

                return;
            }

            if ($parentId === null || $parentId === '') {
                $validator->errors()->add('parent_id', $type->label().'には上位の'.$expected->label().'が必要です。');

                return;
            }

            $parent = Organization::query()->find($parentId);

            if ($parent !== null && $parent->type !== $expected) {
                $validator->errors()->add('parent_id', $type->label().'の上位組織には'.$expected->label().'を選んでください。');
            }

            // 自分自身や自分の配下を親にはできない
            if ($parent !== null && (int) $parent->id === (int) $this->recordId()) {
                $validator->errors()->add('parent_id', '自分自身を上位組織にはできません。');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '組織名',
            'type' => '種別',
            'prefecture' => '都道府県',
            'parent_id' => '上位組織',
            'is_active' => '状態',
        ];
    }
}
