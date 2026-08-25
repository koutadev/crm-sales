<?php

namespace App\Support\Crm;

use App\Enums\OrganizationType;
use App\Models\Deal;
use App\Models\Organization;

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
     */
    private function __construct(
        public readonly array $regions,
        public readonly int $totalInclTax,
        public readonly int $dealCount,
    ) {}

    public static function build(): self
    {
        $sales = self::salesByEmployee();
        $organizations = self::organizations();

        return self::assemble($sales, $organizations);
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
    private static function salesByEmployee(): array
    {
        /** @var list<object{employee_id: int, employee_name: string, organization_id: int|null, total: int, deals: int}> $rows */
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
            ->get(['id', 'code', 'name', 'type', 'parent_id'])
            ->keyBy('id')
            ->all();
    }

    /**
     * 担当者の金額を、店舗 → エリア → 地域へ足し上げる。
     *
     * @param  list<object{employee_id: int, employee_name: string, organization_id: int|null, total: int, deals: int}>  $sales
     * @param  array<int, Organization>  $organizations
     */
    private static function assemble(array $sales, array $organizations): self
    {
        // 店舗 ID => その店舗に所属する担当者のノード
        $employeesByStore = [];
        $unassigned = [];
        $total = 0;
        $totalDeals = 0;

        foreach ($sales as $row) {
            $amount = (int) $row->total;
            $deals = (int) $row->deals;
            $total += $amount;
            $totalDeals += $deals;

            $node = new OrganizationSalesNode(
                key: 'employee-'.$row->employee_id,
                name: (string) $row->employee_name,
                typeLabel: '担当者',
                depth: 4,
                amountInclTax: $amount,
                dealCount: $deals,
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
            );
        }

        return new self($regions, $total, $totalDeals);
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
