<?php

namespace App\Support\Crm;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\Partner;
use Illuminate\Support\Carbon;

/**
 * 顧客詳細の金額サマリ。
 *
 * 金額はすべて税込(内税統一)。商談に保存済みの amount_total を合算する。
 * 件数と金額をまとめて 1 クエリの条件付き集計で取得する
 * (ダッシュボードの DealMetrics と同じ流儀)。
 */
class CustomerSummary
{
    public function __construct(
        /** 累計売上: 受注済み商談の税込合計 */
        public readonly int $wonTotal,
        /** 進行中商談金額: 受注も失注もしていない商談の税込合計 */
        public readonly int $openTotal,
        /** 受注残: 受注済みのうち、予定クローズ日がまだ到来していない商談の税込合計 */
        public readonly int $backlogTotal,
        public readonly int $dealCount,
        public readonly int $openCount,
        public readonly int $wonCount,
    ) {}

    public static function for(Partner $partner): self
    {
        $openValues = DealStatus::openValues();
        $openPlaceholders = implode(', ', array_fill(0, count($openValues), '?'));
        $today = Carbon::now()->toDateString();

        $row = Deal::query()
            ->where('partner_id', $partner->id)
            ->toBase()
            ->selectRaw(
                'count(*) as deal_count'
                .', coalesce(sum(case when deals.status = ? then deals.amount_total else 0 end), 0) as won_total'
                .', coalesce(sum(case when deals.status = ? then 1 else 0 end), 0) as won_count'
                ." , coalesce(sum(case when deals.status in ($openPlaceholders) then deals.amount_total else 0 end), 0) as open_total"
                .", coalesce(sum(case when deals.status in ($openPlaceholders) then 1 else 0 end), 0) as open_count"
                .', coalesce(sum(case when deals.status = ? and deals.expected_close_date >= ? then deals.amount_total else 0 end), 0) as backlog_total',
                array_merge(
                    [DealStatus::Won->value, DealStatus::Won->value],
                    $openValues,
                    $openValues,
                    [DealStatus::Won->value, $today],
                ),
            )
            ->first();

        // 集計だけのクエリなので、行は必ず 1 件返る
        assert($row !== null);

        return new self(
            wonTotal: (int) $row->won_total,
            openTotal: (int) $row->open_total,
            backlogTotal: (int) $row->backlog_total,
            dealCount: (int) $row->deal_count,
            openCount: (int) $row->open_count,
            wonCount: (int) $row->won_count,
        );
    }
}
