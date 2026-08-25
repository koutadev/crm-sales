<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\ActivityLog;
use App\Support\Crm\DealHeadline;
use App\Support\Crm\DealMetrics;
use App\Support\Crm\OrganizationSales;
use App\Support\Crm\PipelineRow;
use App\Support\Crm\SalesTargetLookup;
use App\Support\Dashboard\Chart;
use App\Support\Dashboard\Kpi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 売上ダッシュボード。
 *
 * 集計の方針(詳しくは App\Support\Crm\DealMetrics):
 *   - 金額はすべて税込。売上は受注日ベース、見込みは予定クローズ日ベース
 *   - KPI 4 種で 1 クエリ、月次推移・担当者別・ステータス別で各 1 クエリの計 4 クエリ。
 *     月や担当者ごとにループでクエリを回さない
 *   - 期間は固定(KPI は当月・推移は直近 12 か月)。期間フィルタ UI は作っていない
 *
 * 画面は共通基盤のダッシュボード枠(KPI カード + Chart.js)をそのまま使う。
 * 権限を持たないユーザーには、そのブロックごと表示しない。
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        // 商談を見られる権限があるかどうかで、金額のブロックごと出し分ける
        $canViewDeals = $request->user()?->can(PermissionName::MasterView->value) ?? false;
        $canViewLogs = $request->user()?->can(PermissionName::ActivityLogView->value) ?? false;

        if (! $canViewDeals) {
            return view('dashboard', [
                'kpis' => [],
                'charts' => [],
                'pipeline' => [],
                'organizationSales' => null,
                'targets' => null,
                'fiscalLabel' => null,
                'recentActivities' => $canViewLogs ? $this->recentActivities() : null,
            ]);
        }

        $headline = DealMetrics::headline();
        $monthlySales = DealMetrics::monthlySales(12);
        $salesByEmployee = DealMetrics::salesByEmployee();
        $pipeline = DealMetrics::pipeline();

        // 予実(目標は独立テーブル。実績はこれまでの受注集計をそのまま使う)
        [$fiscalStart, $fiscalEnd] = $this->fiscalYearRange();
        $targets = SalesTargetLookup::build(Carbon::now(), $fiscalStart, $fiscalEnd);
        $organizationSales = OrganizationSales::build(Carbon::now(), $targets, $fiscalStart, $fiscalEnd);

        return view('dashboard', [
            'kpis' => $this->kpis($headline),
            'charts' => $this->charts($monthlySales, $salesByEmployee, $pipeline, $targets),
            'pipeline' => $pipeline,
            // 組織別(地域 > エリア > 店舗 > 担当者)。担当者の所属をたどって積み上げる
            'organizationSales' => $organizationSales,
            'targets' => $targets,
            'fiscalLabel' => $fiscalStart->year.'年度',
            'recentActivities' => $canViewLogs ? $this->recentActivities() : null,
        ]);
    }

    /**
     * KPI カード。金額はすべて税込。
     *
     * @return list<Kpi>
     */
    private function kpis(DealHeadline $headline): array
    {
        $thisMonth = Carbon::now()->format('Y年n月');

        return [
            new Kpi(
                label: '今月の受注(税込)',
                value: $headline->wonThisMonth,
                unit: '円',
                href: route('deals.index', ['status' => 'won']),
                note: $thisMonth.'に受注した商談',
            ),
            new Kpi(
                label: '今月の受注見込み(税込)',
                value: $headline->forecastThisMonth,
                unit: '円',
                href: route('deals.index', ['status' => 'open']),
                note: '今月クローズ予定 × 確度',
            ),
            new Kpi(
                label: '進行中の商談',
                value: $headline->openCount,
                unit: '件',
                href: route('deals.index', ['status' => 'open']),
                note: '受注・失注を除く',
            ),
            new Kpi(
                label: '受注残(税込)',
                value: $headline->backlogTotal,
                unit: '円',
                href: route('deals.index', ['status' => 'won']),
                note: '受注済みで納品予定日が未到来',
            ),
        ];
    }

    /**
     * グラフ。
     *
     * @param  array<string, int>  $monthlySales
     * @param  array<string, int>  $salesByEmployee
     * @param  list<PipelineRow>  $pipeline
     * @return list<Chart>
     */
    private function charts(array $monthlySales, array $salesByEmployee, array $pipeline, ?SalesTargetLookup $targets = null): array
    {
        $pipelineAmounts = [];

        foreach ($pipeline as $row) {
            $pipelineAmounts[$row->status->label()] = $row->totalInclTax;
        }

        // 担当者の軸ラベルは苗字だけにして、正式名称はツールチップと読み上げに残す
        [$employeeLabels, $employeeFullNames] = $this->shortenPersonNames($salesByEmployee);

        // 月次推移には全社目標を破線で重ねる(ギャップが見えるように)
        $monthlyTargets = [];

        foreach (array_keys($monthlySales) as $label) {
            // 実績は 'Y/m'、目標は 'Y-m' で持っているのでここで合わせる
            $monthlyTargets[$label] = $targets?->companySeries()[str_replace('/', '-', (string) $label)] ?? 0;
        }

        return [
            Chart::line('monthly-sales', '月次売上推移（受注日ベース・税込）', $monthlySales, '受注金額')
                ->withComparison('全社目標', $monthlyTargets),
            Chart::bar('sales-by-employee', '担当者別の売上（受注・税込）', $employeeLabels, '受注金額')
                ->withTooltipLabels($employeeFullNames),
            Chart::bar('pipeline-amount', 'ステータス別の商談金額（税込）', $pipelineAmounts, '商談金額'),
        ];
    }

    /**
     * 氏名を軸ラベル用に短くする(「姓 名」の姓だけ)。
     *
     * 区切りの空白が無ければそのまま。同じ姓が複数いる場合は、
     * 取り違えないようその人たちだけフルネームのままにする。
     *
     * @param  array<string, int>  $values  [氏名 => 値]
     * @return array{0: array<string, int>, 1: list<string>} [短い表記の値, 元の氏名]
     */
    private function shortenPersonNames(array $values): array
    {
        $fullNames = array_map(strval(...), array_keys($values));

        $surnames = array_map(
            static fn (string $name): string => preg_split('/[\s　]+/u', trim($name))[0] ?? $name,
            $fullNames,
        );

        $duplicated = array_keys(array_filter(array_count_values($surnames), static fn (int $count): bool => $count > 1));

        $shortened = [];

        foreach (array_values($values) as $index => $value) {
            $surname = $surnames[$index];
            $label = in_array($surname, $duplicated, true) ? $fullNames[$index] : $surname;

            $shortened[$label] = $value;
        }

        return [$shortened, $fullNames];
    }

    /**
     * 年度（4 月始まり）の範囲。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function fiscalYearRange(): array
    {
        $startMonth = (int) config('ui.fiscal_year_start_month', 4);
        $today = Carbon::now();

        $start = $today->copy()->startOfMonth()->month($startMonth);

        if ($today->month < $startMonth) {
            $start = $start->subYear();
        }

        return [$start, $start->copy()->addYear()->subMonth()->endOfMonth()];
    }

    /**
     * 最近の操作ログ。
     *
     * @return Collection<int, ActivityLog>
     */
    private function recentActivities(): Collection
    {
        return ActivityLog::query()
            ->with('user:id,name')
            ->newestFirst()
            ->limit(8)
            ->get();
    }
}
