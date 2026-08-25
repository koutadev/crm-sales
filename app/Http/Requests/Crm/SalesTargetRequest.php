<?php

namespace App\Http\Requests\Crm;

use App\Enums\TargetScope;
use App\Http\Requests\Masters\MasterRequest;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\SalesTarget;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 売上目標の登録・編集。
 *
 * 粒度によって対象の参照先（組織 / 社員）が変わるので、その整合をここで見る。
 */
class SalesTargetRequest extends MasterRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::enum(TargetScope::class)],
            'target_id' => ['bail', 'nullable', 'integer'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $scope = TargetScope::tryFrom((string) $this->input('scope'));

            if ($scope === null) {
                return;
            }

            $targetId = $this->input('target_id');
            $targetId = $targetId === '' ? null : $targetId;

            if (! $scope->needsTarget()) {
                if ($targetId !== null) {
                    $validator->errors()->add('target_id', '全社の目標に対象は指定できません。');
                }
            } elseif ($targetId === null) {
                $validator->errors()->add('target_id', $scope->label().'を選んでください。');
            } else {
                $this->checkTargetExists($validator, $scope, (int) $targetId);
            }

            $this->checkNotDuplicated($validator, $scope, $targetId === null ? null : (int) $targetId);
        });
    }

    /**
     * 対象が実在し、粒度と種別が合っているか。
     */
    private function checkTargetExists(Validator $validator, TargetScope $scope, int $targetId): void
    {
        if ($scope === TargetScope::Employee) {
            $exists = Employee::query()->whereKey($targetId)->exists();

            if (! $exists) {
                $validator->errors()->add('target_id', '担当者が見つかりません。');
            }

            return;
        }

        $organization = Organization::query()->find($targetId);

        if ($organization === null || $organization->type !== $scope->organizationType()) {
            $validator->errors()->add('target_id', $scope->label().'を選んでください。');
        }
    }

    /**
     * 同じ対象・同じ年月の目標は 1 本だけ。
     */
    private function checkNotDuplicated(Validator $validator, TargetScope $scope, ?int $targetId): void
    {
        $exists = SalesTarget::query()
            ->where('scope', $scope->value)
            ->when($targetId === null,
                static fn ($query) => $query->whereNull('target_id'),
                static fn ($query) => $query->where('target_id', $targetId),
            )
            ->where('year', (int) $this->input('year'))
            ->where('month', (int) $this->input('month'))
            ->when($this->recordId() !== null, fn ($query) => $query->whereKeyNot($this->recordId()))
            ->exists();

        if ($exists) {
            $validator->errors()->add('year', 'この対象・この年月の目標はすでに登録されています。');
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scope' => '粒度',
            'target_id' => '対象',
            'year' => '年',
            'month' => '月',
            'amount' => '目標金額',
            'is_active' => '状態',
        ];
    }
}
