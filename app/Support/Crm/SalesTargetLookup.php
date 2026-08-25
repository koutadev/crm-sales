<?php

namespace App\Support\Crm;

use App\Enums\TargetScope;
use App\Models\SalesTarget;
use Illuminate\Support\Carbon;

/**
 * ダッシュボードで使う目標を、1 クエリでまとめて引く。
 *
 * 取るのは 2 種類だけ。
 *   - 当月のすべての粒度（全社 / 地域 / エリア / 店舗 / 担当者）
 *   - 全社の月次（グラフの目標ラインと、年度の合計に使う）
 */
class SalesTargetLookup
{
    /**
     * @param  array<string, int>  $monthly  ['store:12' => 3000000]（当月ぶん）
     * @param  array<string, int>  $companySeries  ['2026-08' => 22100000]
     */
    private function __construct(
        private readonly array $monthly,
        private readonly array $companySeries,
        private readonly int $fiscalTotal,
    ) {}

    public static function build(Carbon $month, Carbon $fiscalStart, Carbon $fiscalEnd, int $seriesMonths = 12): self
    {
        $seriesFrom = $month->copy()->startOfMonth()->subMonths($seriesMonths - 1);

        // 全社の月次は「グラフの範囲」と「年度」の両方を含む幅で取る
        $from = $seriesFrom->lessThan($fiscalStart) ? $seriesFrom : $fiscalStart;
        $to = $month->greaterThan($fiscalEnd) ? $month : $fiscalEnd;

        $rows = SalesTarget::query()
            ->active()
            ->where(function ($query) use ($month, $from, $to): void {
                // 当月のすべての粒度
                $query->where(function ($current) use ($month): void {
                    $current->where('year', $month->year)->where('month', $month->month);
                })
                    // 全社の月次(グラフの目標ライン・年度合計)
                    ->orWhere(function ($company) use ($from, $to): void {
                        $company->where('scope', TargetScope::Company->value)
                            ->whereRaw('(year * 100 + month) between ? and ?', [
                                $from->year * 100 + $from->month,
                                $to->year * 100 + $to->month,
                            ]);
                    });
            })
            ->get(['scope', 'target_id', 'year', 'month', 'amount']);

        $monthly = [];
        $series = [];
        $fiscalTotal = 0;

        foreach ($rows as $row) {
            $period = sprintf('%04d-%02d', $row->year, $row->month);

            if ($row->year === $month->year && $row->month === $month->month) {
                $monthly[self::key($row->scope, $row->target_id)] = (int) $row->amount;
            }

            if ($row->scope !== TargetScope::Company) {
                continue;
            }

            $series[$period] = (int) $row->amount;

            $date = Carbon::create($row->year, $row->month, 1);

            if ($date !== null && $date->betweenIncluded($fiscalStart, $fiscalEnd)) {
                $fiscalTotal += (int) $row->amount;
            }
        }

        ksort($series);

        return new self($monthly, $series, $fiscalTotal);
    }

    /**
     * 当月の目標（未設定なら 0）。
     */
    public function monthly(TargetScope $scope, ?int $targetId = null): int
    {
        return $this->monthly[self::key($scope, $targetId)] ?? 0;
    }

    /**
     * 全社の月次目標（グラフの目標ライン用）。
     *
     * @return array<string, int> ['2026-08' => 22100000]
     */
    public function companySeries(): array
    {
        return $this->companySeries;
    }

    /**
     * 年度の全社目標合計。
     */
    public function fiscalTotal(): int
    {
        return $this->fiscalTotal;
    }

    private static function key(TargetScope $scope, ?int $targetId): string
    {
        return $scope->value.':'.($targetId ?? '-');
    }
}
