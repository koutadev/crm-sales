<?php

namespace App\Support\Ui\Contracts;

use Carbon\CarbonInterface;

/**
 * カレンダーに「特別な日」を差し込むための口。
 *
 * 既定の実装(NullHolidayProvider)は何も返さない。
 * 祝日をハイライトしたくなったら、この契約を実装したクラスを
 * サービスプロバイダで差し替えるだけでカレンダー部品に反映される。
 *
 *   // 例: AppServiceProvider
 *   $this->app->bind(HolidayProvider::class, JapaneseHolidayProvider::class);
 */
interface HolidayProvider
{
    /**
     * 指定期間の特別な日を返す。
     *
     * @return array<string, string> [Y-m-d => 表示名(例: 海の日)]
     */
    public function between(CarbonInterface $from, CarbonInterface $to): array;
}
