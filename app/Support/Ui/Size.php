<?php

namespace App\Support\Ui;

/**
 * ボタン・入力欄の大きさ。
 */
enum Size: string
{
    /**
     * モバイルで確保する最小のタップ領域（44px）。
     *
     * 指で押す部品は 44px 四方が目安（iOS / Android のガイドライン）。
     * sm 以上では min-h-0 に戻すので、デスクトップの高さ・見た目は変わらない。
     * ボタン以外（リンク・ページ送りなど）にもこの文字列をそのまま足して使う。
     */
    public const TOUCH = 'inline-flex items-center min-h-11 sm:min-h-0';

    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';

    public static function resolve(self|string|null $value, self $default = self::Md): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return $value === null ? $default : (self::tryFrom($value) ?? $default);
    }

    public function buttonClasses(): string
    {
        $size = match ($this) {
            self::Sm => 'gap-1.5 px-2.5 py-1.5 text-xs',
            self::Md => 'gap-2 px-4 py-2 text-sm',
            self::Lg => 'gap-2 px-5 py-2.5 text-base',
        };

        // モバイルでは指で押せる大きさを確保する（デスクトップは従来どおり）
        return $size.' min-h-11 sm:min-h-0';
    }

    public function inputClasses(): string
    {
        $size = match ($this) {
            self::Sm => 'px-2.5 py-1.5 text-xs',
            self::Md => 'px-3 py-2 text-sm',
            self::Lg => 'px-4 py-2.5 text-base',
        };

        // 入力欄もモバイルでは 44px 以上にする（デスクトップは従来どおり）
        return $size.' min-h-11 sm:min-h-0';
    }

    public function iconClasses(): string
    {
        return match ($this) {
            self::Sm => 'h-3.5 w-3.5',
            self::Md => 'h-4 w-4',
            self::Lg => 'h-5 w-5',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sm => '小',
            self::Md => '中',
            self::Lg => '大',
        };
    }
}
