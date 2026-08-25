<?php

namespace App\Support\Crm;

use App\Enums\DealStatus;
use App\Models\Deal;
use Illuminate\Support\Collection;

/**
 * カンバンの 1 列(1 ステータス)。
 *
 * count / amountInclTax は絞り込み後の全件に対する値なので、
 * カードを上限で切っていても列ヘッダーの数字は正しい。
 */
class DealKanbanLane
{
    /**
     * @param  Collection<int, Deal>  $deals
     */
    public function __construct(
        public readonly DealStatus $status,
        public readonly Collection $deals,
        public readonly int $count,
        public readonly int $amountInclTax,
    ) {}

    /**
     * 上限で表示しきれなかった件数。
     */
    public function hiddenCount(): int
    {
        return max(0, $this->count - $this->deals->count());
    }
}
