<?php

namespace App\Support\Ui;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * 日付範囲ピッカーの相対プリセット。
 *
 * 保存されるのは「今月」などの**キー**であって日付ではない。
 * 期間は参照するたびに基準日から計算し直すため、月が替わっても指定し直す必要がない。
 */
enum DateRangePreset: string
{
    /** 指定なし(全期間) */
    case None = 'none';

    /** 開始日・終了日を直接指定 */
    case Custom = 'custom';

    case Today = 'today';
    case ThisWeek = 'this_week';
    case ThisMonth = 'this_month';
    case ThisQuarter = 'this_quarter';
    case ThisFiscalYear = 'this_fiscal_year';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Last90Days = 'last_90_days';

    public static function resolve(self|string|null $value, self $default = self::None): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return $value === null ? $default : (self::tryFrom($value) ?? $default);
    }

    public function label(): string
    {
        return match ($this) {
            self::None => '指定なし',
            self::Custom => 'カスタム',
            self::Today => '今日',
            self::ThisWeek => '今週',
            self::ThisMonth => '今月',
            self::ThisQuarter => '今四半期',
            self::ThisFiscalYear => '今年度',
            self::Last7Days => '過去 7 日',
            self::Last30Days => '過去 30 日',
            self::Last90Days => '過去 90 日',
        };
    }

    /**
     * 相対プリセット(ワンクリックで選べるもの)だけを並べる。
     *
     * @return list<self>
     */
    public static function relative(): array
    {
        return [
            self::Today,
            self::ThisWeek,
            self::ThisMonth,
            self::ThisQuarter,
            self::ThisFiscalYear,
            self::Last7Days,
            self::Last30Days,
            self::Last90Days,
        ];
    }

    public function isRelative(): bool
    {
        return in_array($this, self::relative(), true);
    }

    /**
     * 基準日から期間を計算する。
     *
     * 「指定なし」と「カスタム」は自分では期間を決められないので null を返す。
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null [開始日, 終了日]
     */
    public function range(?CarbonInterface $asOf = null): ?array
    {
        $today = ($asOf !== null ? CarbonImmutable::instance($asOf) : CarbonImmutable::now())->startOfDay();

        return match ($this) {
            self::None, self::Custom => null,

            self::Today => [$today, $today],
            self::ThisWeek => [
                $today->startOfWeek((int) config('ui.week_starts_on', 1)),
                $today->startOfWeek((int) config('ui.week_starts_on', 1))->addDays(6),
            ],
            self::ThisMonth => [$today->startOfMonth(), $today->endOfMonth()->startOfDay()],
            self::ThisQuarter => [$today->startOfQuarter(), $today->endOfQuarter()->startOfDay()],
            self::ThisFiscalYear => self::fiscalYear($today),

            // 「過去 N 日」は今日を含む N 日間
            self::Last7Days => [$today->subDays(6), $today],
            self::Last30Days => [$today->subDays(29), $today],
            self::Last90Days => [$today->subDays(89), $today],
        };
    }

    /**
     * 今年度(config/ui.php の fiscal_year_start_month 始まり)。
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function fiscalYear(CarbonImmutable $today): array
    {
        $startMonth = (int) config('ui.fiscal_year_start_month', 4);

        $year = $today->month >= $startMonth ? $today->year : $today->year - 1;

        $start = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();

        return [$start, $start->addYear()->subDay()];
    }
}
