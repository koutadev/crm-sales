<?php

namespace App\Support\Crm;

use App\Models\Deal;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;

/**
 * 顧客詳細の金額サマリ。
 *
 * 金額はすべて税込(内税統一)。本格的な集計はダッシュボード(STEP 6)で作り込むため、
 * ここでは商談に保存済みの amount_total を素直に合算している。
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
        return new self(
            wonTotal: (int) self::deals($partner)->won()->sum('amount_total'),
            openTotal: (int) self::deals($partner)->open()->sum('amount_total'),
            backlogTotal: (int) self::deals($partner)->backlog()->sum('amount_total'),
            dealCount: self::deals($partner)->count(),
            openCount: self::deals($partner)->open()->count(),
            wonCount: self::deals($partner)->won()->count(),
        );
    }

    /**
     * @return Builder<Deal>
     */
    private static function deals(Partner $partner): Builder
    {
        return Deal::query()->where('partner_id', $partner->id);
    }
}
