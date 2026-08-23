<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * 商談ステータス(営業パイプラインの段階)。
 *
 * 「受注」「失注」がクローズ済み、それ以外が進行中。
 * 進行中の商談金額が「見込み」、受注済みの金額が「売上」として集計される。
 */
enum DealStatus: string
{
    use HasOptions;

    /** 見込み(引き合い・初期接触) */
    case Prospect = 'prospect';

    /** 提案中 */
    case Proposing = 'proposing';

    /** 見積提示 */
    case Quoted = 'quoted';

    /** 受注(売上として確定) */
    case Won = 'won';

    /** 失注 */
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => '見込み',
            self::Proposing => '提案中',
            self::Quoted => '見積提示',
            self::Won => '受注',
            self::Lost => '失注',
        };
    }

    /**
     * 一覧のバッジ色(Tailwind のクラス)。
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Prospect => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            self::Proposing => 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
            self::Quoted => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
            self::Won => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
            self::Lost => 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200',
        };
    }

    /**
     * 進行中(まだ受注も失注もしていない)か。
     */
    public function isOpen(): bool
    {
        return ! $this->isClosed();
    }

    /**
     * クローズ済み(受注 または 失注)か。
     */
    public function isClosed(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }

    public function isWon(): bool
    {
        return $this === self::Won;
    }

    /**
     * 進行中ステータスの値一覧(見込み金額の集計に使う)。
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isOpen()),
        ));
    }
}
