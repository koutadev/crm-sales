<?php

namespace App\Support\Crm;

use App\Enums\TargetScope;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\SalesTarget;

/**
 * 売上目標の「対象」の表示名を引く。
 *
 * 対象は粒度によって参照先が変わる（組織 or 社員）ので、
 * 一覧のたびに 1 件ずつ引かないよう、まとめて 2 クエリで解決する。
 */
class TargetLabels
{
    /**
     * @param  array<int, string>  $organizations
     * @param  array<int, string>  $employees
     */
    private function __construct(
        private readonly array $organizations,
        private readonly array $employees,
    ) {}

    /**
     * 画面に出す目標ぶんだけをまとめて解決する。
     *
     * @param  iterable<array-key, mixed>  $targets
     */
    public static function for(iterable $targets): self
    {
        $organizationIds = [];
        $employeeIds = [];

        foreach ($targets as $target) {
            if (! $target instanceof SalesTarget || $target->target_id === null) {
                continue;
            }

            if ($target->scope === TargetScope::Employee) {
                $employeeIds[] = $target->target_id;

                continue;
            }

            $organizationIds[] = $target->target_id;
        }

        return new self(
            $organizationIds === [] ? [] : Organization::query()->whereKey($organizationIds)->pluck('name', 'id')->all(),
            $employeeIds === [] ? [] : Employee::query()->whereKey($employeeIds)->pluck('name', 'id')->all(),
        );
    }

    public function of(SalesTarget $target): string
    {
        if (! $target->scope->needsTarget()) {
            return TargetScope::Company->label();
        }

        if ($target->target_id === null) {
            return '—';
        }

        $name = $target->scope === TargetScope::Employee
            ? ($this->employees[$target->target_id] ?? null)
            : ($this->organizations[$target->target_id] ?? null);

        return $name ?? '（削除済み）';
    }
}
