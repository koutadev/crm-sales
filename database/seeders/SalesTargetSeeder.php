<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Enums\TargetScope;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\SalesTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 売上目標のサンプル（本番環境では実行しない）。
 *
 * 直近 12 か月ぶんを、全社 → 地域 → エリア → 店舗 → 担当者 の各粒度で用意する。
 * 金額は「実績と同じくらい」に見えるよう、実績を元に切りのいい値へ丸めて作るので、
 * 達成率が 60〜130% あたりに散らばる（ゲージの色が確認できる）。
 *
 * 乱数は使わないため、何度シードしても同じ目標になる。
 */
class SalesTargetSeeder extends Seeder
{
    /** 何か月ぶん作るか。 */
    private const MONTHS = 12;

    public function run(): void
    {
        if (SalesTarget::query()->exists()) {
            return;
        }

        config(['activity_log.enabled' => false]);

        try {
            $actuals = $this->monthlyActuals();
            $stores = Organization::query()->ofType(OrganizationType::Store)->with('parent')->get();
            $employees = Employee::query()->active()->whereNotNull('organization_id')->get();

            $month = Carbon::now()->startOfMonth()->subMonths(self::MONTHS - 1);

            for ($index = 0; $index < self::MONTHS; $index++, $month = $month->addMonth()) {
                $this->createForMonth($month, $actuals, $stores, $employees);
            }
        } finally {
            config(['activity_log.enabled' => true]);
        }
    }

    /**
     * 月ごとの受注実績（目標の目安に使う）。
     *
     * @return array<string, int> ['2026-08' => 12345678]
     */
    private function monthlyActuals(): array
    {
        return DealMetricsSnapshot::monthlyWonTotals(self::MONTHS);
    }

    /**
     * @param  array<string, int>  $actuals
     * @param  Collection<int, Organization>  $stores
     * @param  Collection<int, Employee>  $employees
     */
    private function createForMonth(Carbon $month, array $actuals, $stores, $employees): void
    {
        $key = $month->format('Y-m');
        $actual = $actuals[$key] ?? 0;

        // 実績が無い月も、全社の目標だけは置いておく
        $companyTarget = $this->round($actual > 0 ? $actual * 1.05 : 25000000);

        SalesTarget::create([
            'scope' => TargetScope::Company,
            'target_id' => null,
            'year' => $month->year,
            'month' => $month->month,
            'amount' => $companyTarget,
            'is_active' => true,
        ]);

        // 店舗数で割って、店舗 → エリア → 地域 と積み上げる
        $storeCount = max(1, $stores->count());
        $perStore = $this->round(intdiv($companyTarget, $storeCount));

        $byArea = [];
        $byRegion = [];

        foreach ($stores as $store) {
            SalesTarget::create([
                'scope' => TargetScope::Store,
                'target_id' => $store->id,
                'year' => $month->year,
                'month' => $month->month,
                'amount' => $perStore,
                'is_active' => true,
            ]);

            $areaId = (int) $store->parent_id;
            $byArea[$areaId] = ($byArea[$areaId] ?? 0) + $perStore;

            $regionId = (int) ($store->parent->parent_id ?? 0);
            $byRegion[$regionId] = ($byRegion[$regionId] ?? 0) + $perStore;
        }

        foreach ($byArea as $areaId => $amount) {
            SalesTarget::create([
                'scope' => TargetScope::Area,
                'target_id' => $areaId,
                'year' => $month->year,
                'month' => $month->month,
                'amount' => $amount,
                'is_active' => true,
            ]);
        }

        foreach ($byRegion as $regionId => $amount) {
            if ($regionId === 0) {
                continue;
            }

            SalesTarget::create([
                'scope' => TargetScope::Region,
                'target_id' => $regionId,
                'year' => $month->year,
                'month' => $month->month,
                'amount' => $amount,
                'is_active' => true,
            ]);
        }

        // 担当者は当月ぶんだけ（毎月ぶん作ると件数が多くなりすぎるため）
        if (! $month->isSameMonth(Carbon::now())) {
            return;
        }

        foreach ($employees as $employee) {
            SalesTarget::create([
                'scope' => TargetScope::Employee,
                'target_id' => $employee->id,
                'year' => $month->year,
                'month' => $month->month,
                'amount' => $this->round(intdiv($perStore, 2)),
                'is_active' => true,
            ]);
        }
    }

    /**
     * 10 万円単位に丸める（目標らしい切りのいい数字にする）。
     */
    private function round(int|float $amount): int
    {
        return (int) (round($amount / 100000) * 100000);
    }
}
