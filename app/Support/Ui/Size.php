<?php

namespace App\Support\Ui;

/**
 * ボタン・入力欄の大きさ。
 */
enum Size: string
{
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
        return match ($this) {
            self::Sm => 'gap-1.5 px-2.5 py-1.5 text-xs',
            self::Md => 'gap-2 px-4 py-2 text-sm',
            self::Lg => 'gap-2 px-5 py-2.5 text-base',
        };
    }

    public function inputClasses(): string
    {
        return match ($this) {
            self::Sm => 'px-2.5 py-1.5 text-xs',
            self::Md => 'px-3 py-2 text-sm',
            self::Lg => 'px-4 py-2.5 text-base',
        };
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
