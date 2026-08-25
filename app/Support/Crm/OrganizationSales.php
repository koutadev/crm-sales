<?php

namespace App\Support\Crm;

use App\Enums\OrganizationType;
use App\Enums\TargetScope;
use App\Models\Deal;
use App\Models\Organization;
use App\Support\Ui\Achievement;
use Illuminate\Support\Carbon;

/**
 * 組織別（地域 > エリア > 店舗 > 担当者）の受注売上。
 *
 * 集計の考え方:
 *   - 商談テーブルには組織を持たせない。担当者(employee)の所属組織をたどって積み上げる
 *   - 読むクエリは 2 本だけ
 *       1) 担当者ごとの受注金額・件数（deals × employees を 1 回 group by）
 *       2) 組織の一覧（地域 + エリア + 店舗。数十行）
 *     あとは PHP 側で「担当者 → 店舗 → エリア → 地域」に足し上げる。
 *     階層は 3 段に固定されているので再帰は要らず、行数は組織数 + 担当者数で頭打ちになる。
 *   - 金額はすべて税込。商談に保存済みの amount_total をそのまま合計する
 *     （金額の計算ロジックには一切触れない）
 */
class OrganizationSales
{
    /** 所属が未設定の担当者をまとめる見出し。 */
    private const UNASSIGNED = '未所属';

    /**
     * @param  list<OrganizationSalesNode>  $regions
     * @param  list<OrganizationSalesNode>  $prefectures  都道府県別に束ねた見方(階層とは別の切り口)
     */
    private function __construct(
        public readonly array $regions,
        public readonly int $totalInclTax,
        public readonly int $dealCount,
        public readonly int $monthAmount = 0,
        public readonly int $monthTarget = 0,
        public readonly array $prefectures = [],
        /** 年度ぶんの実績(税込) */
        public readonly int $fiscalAmount = 0,
    ) {}

    /**
     * @param  Carbon|null  $month  当月の実績・目標を出す対象月(省略時は今月)
     */
    public static function build(
        ?Carbon $month = null,
        ?SalesTargetLookup $targets = null,
        ?Carbon $fiscalStart = null,
        ?Carbon $fiscalEnd = null,
    ): self {
        $month = ($month ?? Carbon::now())->copy()->startOfMonth();

        /** @var list<object{employee_id: int, employee_name: string, organization_id: int|null, total: int, deals: int, month_total: int, fiscal_total: int}> $sales */
        $sales = self::salesByEmployee($month, $fiscalStart, $fiscalEnd);
        $organizations = self::organizations();

        return self::assemble($sales, $organizations, $targets);
    }

    /**
     * 当月の達成率。
     */
    public function achievement(): Achievement
    {
        return Achievement::of($this->monthAmount, $this->monthTarget);
    }

    public function isEmpty(): bool
    {
        return $this->regions === [];
    }

    /**
     * 担当者ごとの受注金額と件数。1 クエリ。
     *
     * @return list<object{employee_id: int, employee_name: string, organization_id: int|null, total: int, deals: int}>
     */
    /**
     * @return list<object>
     */
    private static function salesByEmployee(Carbon $month, ?Carbon $fiscalStart, ?Carbon $fiscalEnd): array
    {
        // 年度の範囲が渡されなければ、当月だけを見る
        $fiscalStart ??= $month;
        $fiscalEnd ??= $month->copy()->endOfMonth();

        /** @var list<object{employee_id: int, employee_name: string, organization_id: int|null, total: int, deals: int, month_total: int, fiscal_total: int}> $rows */
        $rows = Deal::query()
            ->won()
            ->join('employees', 'employees.id', '=', 'deals.employee_id')
            ->toBase()
            ->selectRaw(
                'employees.id as employee_id'
                .', employees.name as employee_name'
                .', employees.organization_id as organization_id'
                .', coalesce(sum(deals.amount_total), 0) as total'
                .', count(*) as deals'
                // 当月ぶん(達成率の分子)も同じクエリの中で出す
                .', coalesce(sum(case when deals.ordered_at between ? and ? then deals.amount_total else 0 end), 0) as month_total'
                // 年度ぶん(年度の達成率に使う)
                .', coalesce(sum(case when deals.ordered_at between ? and ? then deals.amount_total else 0 end), 0) as fiscal_total',
                [
                    $month->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                    $fiscalStart->toDateString(),
                    $fiscalEnd->toDateString(),
                ],
            )
            ->groupBy('employees.id', 'employees.name', 'employees.organization_id')
            ->orderByDesc('total')
            ->get()
            ->all();

        return $rows;
    }

    /**
     * 組織の一覧。1 クエリ（階層は 3 段なので数十行で収まる）。
     *
     * @return array<int, Organization>
     */
    private static function organizations(): array
    {
        return Organization::query()
            ->orderBy('type')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'parent_id', 'prefecture'])
            ->keyBy('id')
            ->all();
    }

    /**
     * 担当者の金額を、店舗 → エリア → 地域へ足し上げる。
     *
     * @param  list<object{employee_id: int, employee_name: string, organization_id: int|null, total: int, deals: int, month_total: int, fiscal_total: int}>  $sales
     * @param  array<int, Organization>  $organizations
     */
    private static function assemble(array $sales, array $organizations, ?SalesTargetLookup $targets): self
    {
        $targetOf = static fn (TargetScope $scope, ?int $id): int => $targets?->monthly($scope, $id) ?? 0;

        // 店舗 ID => その店舗に所属する担当者のノード
        $employeesByStore = [];
        $unassigned = [];
        $total = 0;
        $totalDeals = 0;
        $monthTotal = 0;
        $fiscalTotal = 0;

        foreach ($sales as $row) {
            $amount = (int) $row->total;
            $deals = (int) $row->deals;
            $month = (int) $row->month_total;
            $total += $amount;
            $totalDeals += $deals;
            $monthTotal += $month;
            $fiscalTotal += (int) $row->fiscal_total;

            $node = new OrganizationSalesNode(
                key: 'employee-'.$row->employee_id,
                name: (string) $row->employee_name,
                typeLabel: '担当者',
                depth: 4,
                amountInclTax: $amount,
                dealCount: $deals,
                monthAmount: $month,
                monthTarget: $targetOf(TargetScope::Employee, (int) $row->employee_id),
            );

            $storeId = $row->organization_id !== null ? (int) $row->organization_id : null;

            if ($storeId === null || ! isset($organizations[$storeId])) {
                $unassigned[] = $node;

                continue;
            }

            $employeesByStore[$storeId][] = $node;
        }

        $regions = [];

        foreach ($organizations as $region) {
            if ($region->type !== OrganizationType::Region) {
                continue;
            }

            $areas = [];

            foreach ($organizations as $area) {
                if ($area->type !== OrganizationType::Area || (int) $area->parent_id !== (int) $region->id) {
                    continue;
                }

                $stores = [];

                foreach ($organizations as $store) {
                    if ($store->type !== OrganizationType::Store || (int) $store->parent_id !== (int) $area->id) {
                        continue;
                    }

                    $members = $employeesByStore[(int) $store->id] ?? [];

                    $stores[] = new OrganizationSalesNode(
                        key: 'store-'.$store->id,
                        name: $store->name,
                        typeLabel: OrganizationType::Store->label(),
                        depth: 3,
                        amountInclTax: self::sum($members),
                        dealCount: self::countDeals($members),
                        children: $members,
                        monthAmount: self::sumMonth($members),
                        monthTarget: $targetOf(TargetScope::Store, (int) $store->id),
                        prefecture: $store->prefecture,
                    );
                }

                $areas[] = new OrganizationSalesNode(
                    key: 'area-'.$area->id,
                    name: $area->name,
                    typeLabel: OrganizationType::Area->label(),
                    depth: 2,
                    amountInclTax: self::sum($stores),
                    dealCount: self::countDeals($stores),
                    children: $stores,
                    monthAmount: self::sumMonth($stores),
                    monthTarget: $targetOf(TargetScope::Area, (int) $area->id),
                );
            }

            $regions[] = new OrganizationSalesNode(
                key: 'region-'.$region->id,
                name: $region->name,
                typeLabel: OrganizationType::Region->label(),
                depth: 1,
                amountInclTax: self::sum($areas),
                dealCount: self::countDeals($areas),
                children: $areas,
                monthAmount: self::sumMonth($areas),
                monthTarget: $targetOf(TargetScope::Region, (int) $region->id),
            );
        }

        // 金額の多い順に並べる(同額はもとの並び)
        usort($regions, static fn (OrganizationSalesNode $a, OrganizationSalesNode $b): int => $b->amountInclTax <=> $a->amountInclTax);

        if ($unassigned !== []) {
            $regions[] = new OrganizationSalesNode(
                key: 'unassigned',
                name: self::UNASSIGNED,
                typeLabel: '担当者',
                depth: 1,
                amountInclTax: self::sum($unassigned),
                dealCount: self::countDeals($unassigned),
                children: $unassigned,
                monthAmount: self::sumMonth($unassigned),
            );
        }

        return new self(
            $regions,
            $total,
            $totalDeals,
            $monthTotal,
            $targetOf(TargetScope::Company, null),
            self::byPrefecture($regions),
            $fiscalTotal,
        );
    }

    /**
     * 都道府県で束ね直す（階層のドリルダウンとは別の切り口）。
     *
     * 店舗が持つ都道府県で集めるだけなので、追加のクエリは要らない。
     *
     * @param  list<OrganizationSalesNode>  $regions
     * @return list<OrganizationSalesNode>
     */
    private static function byPrefecture(array $regions): array
    {
        /** @var array<string, list<OrganizationSalesNode>> $stores */
        $stores = [];

        foreach ($regions as $region) {
            foreach ($region->children as $area) {
                foreach ($area->children as $store) {
                    $name = $store->prefecture ?? '未設定';
                    $stores[$name][] = $store;
                }
            }
        }

        $nodes = [];

        foreach ($stores as $prefecture => $group) {
            $nodes[] = new OrganizationSalesNode(
                key: 'prefecture-'.$prefecture,
                name: $prefecture,
                typeLabel: '都道府県',
                depth: 1,
                amountInclTax: self::sum($group),
                dealCount: self::countDeals($group),
                children: $group,
                monthAmount: self::sumMonth($group),
                monthTarget: self::sumTarget($group),
                prefecture: $prefecture,
            );
        }

        usort($nodes, static fn (OrganizationSalesNode $a, OrganizationSalesNode $b): int => $b->amountInclTax <=> $a->amountInclTax);

        return $nodes;
    }

    /**
     * @param  list<OrganizationSalesNode>  $nodes
     */
    private static function sumMonth(array $nodes): int
    {
        return array_sum(array_map(static fn (OrganizationSalesNode $node): int => $node->monthAmount, $nodes));
    }

    /**
     * @param  list<OrganizationSalesNode>  $nodes
     */
    private static function sumTarget(array $nodes): int
    {
        return array_sum(array_map(static fn (OrganizationSalesNode $node): int => $node->monthTarget, $nodes));
    }

    /**
     * @param  list<OrganizationSalesNode>  $nodes
     */
    private static function sum(array $nodes): int
    {
        return array_sum(array_map(static fn (OrganizationSalesNode $node): int => $node->amountInclTax, $nodes));
    }

    /**
     * @param  list<OrganizationSalesNode>  $nodes
     */
    private static function countDeals(array $nodes): int
    {
        return array_sum(array_map(static fn (OrganizationSalesNode $node): int => $node->dealCount, $nodes));
    }
}
