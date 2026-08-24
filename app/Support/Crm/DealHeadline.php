<?php

namespace App\Support\Crm;

/**
 * ダッシュボード上部の KPI 用の数値(すべて税込)。
 *
 * 4 つの値を 1 クエリの条件付き集計でまとめて取得する。
 */
class DealHeadline
{
    public function __construct(
        /** 今月の受注金額(受注日が当月の受注済み商談) */
        public readonly int $wonThisMonth,
        /** 今月の受注見込み(予定クローズ日が当月の進行中商談 × 確度) */
        public readonly int $forecastThisMonth,
        /** 進行中の商談件数 */
        public readonly int $openCount,
        /** 受注残(受注済みで予定クローズ日が未到来) */
        public readonly int $backlogTotal,
    ) {}
}
