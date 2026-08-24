<?php

namespace App\Support\Crm;

use App\Enums\DealStatus;
use App\Models\Deal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * 売上ダッシュボードの集計。
 *
 * 設計:
 *   - 金額はすべて税込(内税統一)。商談に保存済みの amount_total を集計する
 *   - 売上は「受注日(ordered_at)」ベース、見込みは「予定クローズ日」ベース
 *   - 集計はどれも 1 クエリ。月ごと・担当者ごとにループでクエリを回さない
 *     (KPI 4 種は条件付き集計で 1 クエリにまとめている)
 *
 * 期間は固定(KPI は当月、推移は直近 12 か月)。期間フィルタ UI は作っていないが、
 * 引数で基準日と月数を渡せるようにしてあるので、UI を足すときはそのまま使える。
 *
 * ※ 月のグループ化に to_char() を使っているため PostgreSQL 前提
 *   (このアプリは PostgreSQL 固定。テストも PostgreSQL で動かしている)。
 */
class DealMetrics
{
    /**
     * KPI 4 種を 1 クエリで取得する。
     */
    public static function headline(?CarbonInterface $asOf = null): DealHeadline
    {
        $today = $asOf !== null ? Carbon::instance($asOf->toDateTime()) : Carbon::now();

        $openValues = DealStatus::openValues();
        $openPlaceholders = implode(', ', array_fill(0, count($openValues), '?'));

        $row = Deal::query()
            ->toBase()
            ->selectRaw(
                'coalesce(sum(case when deals.status = ? and deals.ordered_at between ? and ? then deals.amount_total else 0 end), 0) as won_this_month'
                .", coalesce(sum(case when deals.status in ($openPlaceholders) and deals.expected_close_date between ? and ? then floor(deals.amount_total * deals.probability / 100.0) else 0 end), 0) as forecast_this_month"
                .", coalesce(sum(case when deals.status in ($openPlaceholders) then 1 else 0 end), 0) as open_count"
                .', coalesce(sum(case when deals.status = ? and deals.expected_close_date >= ? then deals.amount_total else 0 end), 0) as backlog_total',
                array_merge(
                    [DealStatus::Won->value, $today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
                    $openValues,
                    [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
                    $openValues,
                    [DealStatus::Won->value, $today->toDateString()],
                ),
            )
            ->first();

        // 集計だけのクエリなので、行は必ず 1 件返る
        assert($row !== null);

        return new DealHeadline(
            wonThisMonth: (int) $row->won_this_month,
            forecastThisMonth: (int) $row->forecast_this_month,
            openCount: (int) $row->open_count,
            backlogTotal: (int) $row->backlog_total,
        );
    }

    /**
     * 月次売上推移(受注日ベース・税込)。1 クエリ。
     *
     * 受注が 1 件も無い月も 0 として並べる(グラフの横軸を欠けさせないため)。
     *
     * @return array<string, int> [YYYY/MM => 税込合計]
     */
    public static function monthlySales(int $months = 12, ?CarbonInterface $asOf = null): array
    {
        $today = $asOf !== null ? Carbon::instance($asOf->toDateTime()) : Carbon::now();

        $start = $today->copy()->startOfMonth()->subMonths($months - 1);
        $end = $today->copy()->endOfMonth();

        $totals = Deal::query()
            ->won()
            ->whereNotNull('ordered_at')
            ->whereBetween('ordered_at', [$start->toDateString(), $end->toDateString()])
            ->toBase()
            ->selectRaw("to_char(deals.ordered_at, 'YYYY-MM') as month, coalesce(sum(deals.amount_total), 0) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $series = [];

        for ($cursor = $start->copy(); $cursor <= $end; $cursor->addMonth()) {
            $series[$cursor->format('Y/m')] = (int) ($totals[$cursor->format('Y-m')] ?? 0);
        }

        return $series;
    }

    /**
     * 担当者別の売上(受注済み・税込)。1 クエリ。
     *
     * @return array<string, int> [担当者名 => 税込合計]（多い順）
     */
    public static function salesByEmployee(int $limit = 10): array
    {
        $rows = Deal::query()
            ->won()
            ->join('employees', 'employees.id', '=', 'deals.employee_id')
            ->toBase()
            ->selectRaw('employees.name as employee_name, coalesce(sum(deals.amount_total), 0) as total')
            ->groupBy('employees.id', 'employees.name')
            ->orderByDesc('total')
            ->orderBy('employees.name')
            ->limit($limit)
            ->get();

        $sales = [];

        foreach ($rows as $row) {
            $sales[(string) $row->employee_name] = (int) $row->total;
        }

        return $sales;
    }

    /**
     * ステータス別の商談金額(パイプライン)。1 クエリ。
     *
     * 0 件のステータスも並べる(パイプラインの段階を欠けさせないため)。
     *
     * @return list<PipelineRow>
     */
    public static function pipeline(): array
    {
        $rows = Deal::query()
            ->toBase()
            ->selectRaw(
                'deals.status'
                .', count(*) as deal_count'
                .', coalesce(sum(deals.amount_total), 0) as total'
                .', coalesce(sum(floor(deals.amount_total * deals.probability / 100.0)), 0) as weighted'
            )
            ->groupBy('deals.status')
            ->get()
            ->keyBy('status');

        $pipeline = [];

        foreach (DealStatus::cases() as $status) {
            $row = $rows->get($status->value);

            $pipeline[] = new PipelineRow(
                status: $status,
                dealCount: (int) ($row->deal_count ?? 0),
                totalInclTax: (int) ($row->total ?? 0),
                weightedTotal: (int) ($row->weighted ?? 0),
            );
        }

        return $pipeline;
    }
}
