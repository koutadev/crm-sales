<?php

namespace App\Support\Crm;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Support\DataTable\TableBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 商談のカンバン(パイプライン)表示。
 *
 * ステータスを列にして、絞り込み後の商談をカードで並べる。
 * 件数と金額は一覧と同じサマリ(1 クエリ)から取り、カードの中身は
 * 「列ごとに上位 N 件」をウィンドウ関数で 1 クエリにまとめて取る。
 * 列を増やしても商談が増えても、クエリ本数は変わらない。
 */
class DealKanban
{
    /** 1 列に並べるカードの上限(超えたぶんは「他 N 件」と出す)。 */
    public const LANE_LIMIT = 50;

    /**
     * @param  list<DealKanbanLane>  $lanes
     */
    private function __construct(
        public readonly array $lanes,
        public readonly DealListSummary $summary,
    ) {}

    public static function for(TableBuilder $builder, DealListSummary $summary): self
    {
        $deals = self::topDealsPerStatus($builder);

        $lanes = [];

        foreach (DealStatus::cases() as $status) {
            $totals = $summary->byStatus[$status->value] ?? ['count' => 0, 'amount' => 0];

            $lanes[] = new DealKanbanLane(
                status: $status,
                deals: $deals->get($status->value, collect()),
                count: (int) $totals['count'],
                amountInclTax: (int) $totals['amount'],
            );
        }

        return new self($lanes, $summary);
    }

    /**
     * 絞り込み後の商談を、ステータスごとに上位 N 件だけ取る。
     *
     * @return Collection<array-key, Collection<int, Deal>>
     */
    private static function topDealsPerStatus(TableBuilder $builder): Collection
    {
        // 表示条件はそのまま、並び順だけカンバン用(予定クローズ日が近い順)に置き換える
        $ranked = $builder->filteredQuery()
            ->reorder()
            ->toBase()
            ->select('deals.id')
            ->selectRaw(
                'row_number() over (partition by deals.status'
                .' order by deals.expected_close_date asc, deals.id desc) as lane_rank'
            );

        $ids = DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('lane_rank', '<=', self::LANE_LIMIT)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return collect();
        }

        $deals = Deal::query()
            ->with(['partner:id,name', 'employee:id,name'])
            ->whereKey($ids)
            ->orderBy('expected_close_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy(static fn (Deal $deal): string => $deal->status->value);

        /** @var Collection<array-key, Collection<int, Deal>> $grouped */
        $grouped = collect($deals->all())->map(static fn ($lane): Collection => collect($lane->all()));

        return $grouped;
    }
}
