<?php

namespace App\Support\Ui;

/**
 * 達成率（実績 ÷ 目標）の状態。
 *
 * 「何 % か」だけでなく、未達 / 達成間近 / 達成 の色分けと、
 * 読み上げ用の説明までここにまとめておく（画面ごとに判定を書かない）。
 */
class Achievement
{
    /** 達成間近とみなす下限（%）。 */
    private const NEAR = 80;

    private function __construct(
        private readonly ?float $rate,
    ) {}

    public static function of(int $actual, int $target): self
    {
        if ($target <= 0) {
            return new self(null);
        }

        return new self(round($actual / $target * 100, 1));
    }

    public function hasTarget(): bool
    {
        return $this->rate !== null;
    }

    public function rate(): float
    {
        return $this->rate ?? 0.0;
    }

    public function rateLabel(): string
    {
        return $this->hasTarget() ? $this->rate().'%' : '—';
    }

    /**
     * 棒の長さ（100% を超えても振り切れないように丸める）。
     */
    public function barWidth(): float
    {
        return min(100.0, max(0.0, $this->rate()));
    }

    public function isAchieved(): bool
    {
        return $this->hasTarget() && $this->rate() >= 100;
    }

    public function isNear(): bool
    {
        return $this->hasTarget() && ! $this->isAchieved() && $this->rate() >= self::NEAR;
    }

    public function label(): string
    {
        if (! $this->hasTarget()) {
            return '目標が設定されていません';
        }

        return match (true) {
            $this->isAchieved() => '達成',
            $this->isNear() => '達成間近',
            default => '未達',
        };
    }

    /**
     * 棒の色。
     */
    public function barClass(): string
    {
        return match (true) {
            ! $this->hasTarget() => 'bg-gray-300 dark:bg-gray-600',
            $this->isAchieved() => 'bg-emerald-600',
            $this->isNear() => 'bg-amber-600',
            default => 'bg-rose-600',
        };
    }

    /**
     * 文字色。
     */
    public function textClass(): string
    {
        return match (true) {
            ! $this->hasTarget() => 'text-gray-500 dark:text-gray-400',
            $this->isAchieved() => 'text-emerald-700 dark:text-emerald-400',
            $this->isNear() => 'text-amber-700 dark:text-amber-400',
            default => 'text-rose-700 dark:text-rose-400',
        };
    }

    /**
     * 目標までの残り（達成していれば 0）。
     */
    public function remaining(int $actual, int $target): int
    {
        return max(0, $target - $actual);
    }

    /**
     * 読み上げ用の説明。
     */
    public function description(int $actual, int $target, string $unit): string
    {
        if (! $this->hasTarget()) {
            return '目標が設定されていません。実績 '.number_format($actual).$unit;
        }

        return sprintf(
            '目標 %s%s に対して実績 %s%s、達成率 %s（%s）',
            number_format($target),
            $unit,
            number_format($actual),
            $unit,
            $this->rateLabel(),
            $this->label(),
        );
    }
}
