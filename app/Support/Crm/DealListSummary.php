<?php

namespace App\Support\Crm;

use App\Enums\DealStatus;
use App\Support\DataTable\TableBuilder;

/**
 * 商談一覧の上部に出す、絞り込み結果に連動した金額サマリ。
 *
 * 一覧の表示条件(検索・絞り込み・削除済みの扱い)をそのまま引き継いだクエリに
 * 集計だけを載せて、1 クエリで取得する(明細や商談を 1 件ずつ読まない)。
 * 金額はすべて税込。
 */
class DealListSummary
{
    public function __construct(
        public readonly int $dealCount,
        /** 表示中の商談の税込合計 */
        public readonly int $totalInclTax,
        /** 受注済みの税込合計(＝売上) */
        public readonly int $wonTotal,
        /** 進行中(受注も失注もしていない)の税込合計 */
        public readonly int $openTotal,
        /** 進行中を確度で加重した見込み金額(税込) */
        public readonly int $weightedOpenTotal,
    ) {}

    public static function for(TableBuilder $builder): self
    {
        $openValues = DealStatus::openValues();
        $openPlaceholders = implode(', ', array_fill(0, count($openValues), '?'));

        $row = $builder->filteredQuery()
            // 集計なので並び順とリレーションの読み込みは不要
            ->reorder()
            ->toBase()
            ->selectRaw(
                'count(*) as deal_count'
                .', coalesce(sum(deals.amount_total), 0) as total_incl_tax'
                .', coalesce(sum(case when deals.status = ? then deals.amount_total else 0 end), 0) as won_total'
                .", coalesce(sum(case when deals.status in ($openPlaceholders) then deals.amount_total else 0 end), 0) as open_total"
                .", coalesce(sum(case when deals.status in ($openPlaceholders) then floor(deals.amount_total * deals.probability / 100.0) else 0 end), 0) as weighted_open_total",
                array_merge([DealStatus::Won->value], $openValues, $openValues),
            )
            ->first();

        // 集計だけのクエリなので、行は必ず 1 件返る
        assert($row !== null);

        return new self(
            dealCount: (int) $row->deal_count,
            totalInclTax: (int) $row->total_incl_tax,
            wonTotal: (int) $row->won_total,
            openTotal: (int) $row->open_total,
            weightedOpenTotal: (int) $row->weighted_open_total,
        );
    }
}
