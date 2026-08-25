<?php

namespace Database\Seeders;

use App\Enums\DealStatus;
use App\Models\Deal;
use Illuminate\Support\Carbon;

/**
 * シード用に、受注実績を月ごとに読むだけの小さな道具。
 *
 * 目標のサンプルを「実績と同じくらい」に見せるために使う。
 * 集計そのもの（画面で使う DealMetrics）には手を入れない。
 */
class DealMetricsSnapshot
{
    /**
     * 直近 N か月の受注金額（税込）。
     *
     * @return array<string, int> ['2026-08' => 12345678]
     */
    public static function monthlyWonTotals(int $months): array
    {
        $from = Carbon::now()->startOfMonth()->subMonths($months - 1);

        /** @var array<string, int> $totals */
        $totals = Deal::query()
            ->where('status', DealStatus::Won->value)
            ->whereNotNull('ordered_at')
            ->where('ordered_at', '>=', $from->toDateString())
            ->toBase()
            ->selectRaw("to_char(ordered_at, 'YYYY-MM') as period, coalesce(sum(amount_total), 0) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->map(static fn ($total): int => (int) $total)
            ->all();

        return $totals;
    }
}
