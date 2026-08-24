<?php

namespace App\Support\Ui;

/**
 * バッジ・トーストなどの意味づけ(色)。
 *
 * ステータス表示は「色そのもの」ではなく意味で指定する
 * (例: <x-badge tone="success">受注</x-badge>)。
 */
enum Tone: string
{
    case Neutral = 'neutral';
    case Primary = 'primary';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Info = 'info';

    public static function resolve(self|string|null $value, self $default = self::Neutral): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return $value === null ? $default : (self::tryFrom($value) ?? $default);
    }

    /**
     * バッジ(淡い背景 + 濃い文字)。
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Neutral => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            self::Primary => 'bg-primary-soft text-primary-soft-fg',
            self::Success => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
            self::Warning => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
            self::Danger => 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200',
            self::Info => 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
        };
    }

    /**
     * トースト・インライン通知(枠線つき)。
     */
    public function alertClasses(): string
    {
        return match ($this) {
            self::Neutral => 'border-gray-200 bg-white text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100',
            self::Primary => 'border-primary bg-primary-soft text-primary-soft-fg',
            self::Success => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-100',
            self::Warning => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/60 dark:text-amber-100',
            self::Danger => 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-900/60 dark:text-rose-100',
            self::Info => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-900/60 dark:text-sky-100',
        };
    }

    /**
     * 点(ステータスチップの先頭に置く丸)。
     */
    public function dotClasses(): string
    {
        return match ($this) {
            self::Neutral => 'bg-gray-400',
            self::Primary => 'bg-primary',
            self::Success => 'bg-emerald-500',
            self::Warning => 'bg-amber-500',
            self::Danger => 'bg-rose-500',
            self::Info => 'bg-sky-500',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Neutral => '既定',
            self::Primary => '強調',
            self::Success => '成功',
            self::Warning => '注意',
            self::Danger => 'エラー',
            self::Info => '情報',
        };
    }
}
