<?php

namespace App\Support\DataTable;

/**
 * 一覧の 1 列の定義。
 */
class Column
{
    /**
     * @param  string  $key  ソートに使うカラム名(sortable のとき)
     * @param  string  $label  見出し
     * @param  bool  $sortable  見出しクリックで並び替えできるか
     * @param  string  $align  'left' | 'right' | 'center'
     * @param  bool  $wrap  折り返しを許可するか(false なら 1 行で表示)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $sortable = false,
        public readonly string $align = 'left',
        public readonly bool $wrap = true,
    ) {}

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    public function alignClass(): string
    {
        return match ($this->align) {
            'right' => 'text-right',
            'center' => 'text-center',
            default => 'text-left',
        };
    }
}
