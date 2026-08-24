<?php

namespace App\Support\Crm;

use App\Enums\DealStatus;

/**
 * パイプライン(ステータス別の商談金額)の 1 行。
 */
class PipelineRow
{
    public function __construct(
        public readonly DealStatus $status,
        public readonly int $dealCount,
        /** 税込合計 */
        public readonly int $totalInclTax,
        /** 確度で加重した見込み金額(税込) */
        public readonly int $weightedTotal,
    ) {}
}
