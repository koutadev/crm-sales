<?php

namespace App\Support\Ui;

/**
 * ボタンなどの見た目のバリエーション。
 *
 * 配色はテーマ(config/theme.php)の CSS 変数を参照しているため、
 * .env で primary を変えるとボタンの色もまとめて変わる。
 */
enum Variant: string
{
    /** 主要な操作(保存・登録など。1 画面に 1 つが目安) */
    case Primary = 'primary';

    /** 副次的な操作(キャンセル・戻るなど) */
    case Secondary = 'secondary';

    /** 破壊的な操作(削除など) */
    case Danger = 'danger';

    /** 背景を持たない控えめな操作(一覧の行内リンクなど) */
    case Ghost = 'ghost';

    /**
     * 文字列でも enum でも受け取れるようにする(Blade から使いやすくするため)。
     */
    public static function resolve(self|string|null $value, self $default = self::Secondary): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return $value === null ? $default : (self::tryFrom($value) ?? $default);
    }

    /**
     * ボタンの配色クラス。
     */
    public function buttonClasses(): string
    {
        return match ($this) {
            self::Primary => 'border-transparent bg-primary text-white hover:bg-primary-hover focus-visible:ring-primary',
            self::Secondary => 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus-visible:ring-primary dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
            self::Danger => 'border-transparent bg-rose-600 text-white hover:bg-rose-500 focus-visible:ring-rose-500',
            self::Ghost => 'border-transparent bg-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-primary dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Primary => '主要',
            self::Secondary => '副次',
            self::Danger => '破壊的',
            self::Ghost => '控えめ',
        };
    }
}
