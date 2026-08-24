<?php

namespace App\Support\Ui;

use App\Support\Ui\Contracts\HolidayProvider;
use Carbon\CarbonInterface;

/**
 * 既定の実装。祝日データを持たない。
 *
 * カレンダー部品は「祝日が無い」前提でも成立するので、
 * 祝日対応が必要になった時点で HolidayProvider を差し替える。
 */
class NullHolidayProvider implements HolidayProvider
{
    /**
     * @return array<string, string>
     */
    public function between(CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }
}
