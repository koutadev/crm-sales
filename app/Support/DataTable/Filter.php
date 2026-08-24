<?php

namespace App\Support\DataTable;

/**
 * 一覧の絞り込み条件(セレクトボックス 1 個ぶん)の定義。
 */
class Filter
{
    /**
     * @param  string  $name  クエリパラメータ名
     * @param  string  $label  画面に出す見出し
     * @param  array<array-key, string>  $options  [値 => ラベル](ID など数値キーも可)
     * @param  string|null  $column  絞り込むカラム名(省略時は $name と同じ)
     * @param  string  $placeholder  未選択時の表示
     * @param  array<string, list<string>>  $valueGroups  1 つの選択肢で複数の値を絞り込む場合の [選択値 => 実際の値の一覧]
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly array $options,
        public readonly ?string $column = null,
        public readonly string $placeholder = 'すべて',
        public readonly array $valueGroups = [],
    ) {}

    /**
     * 選択値がまとめ絞り込み(例: 「進行中」= 見込み/提案中/見積提示)なら、その値の一覧を返す。
     *
     * @return list<string>|null
     */
    public function valuesFor(string $value): ?array
    {
        return $this->valueGroups[$value] ?? null;
    }

    public function column(): string
    {
        return $this->column ?? $this->name;
    }

    /**
     * 有効フラグの絞り込み(全マスタ共通)。
     */
    public static function activeFlag(): self
    {
        return new self(
            name: 'is_active',
            label: '状態',
            options: ['1' => '有効', '0' => '無効'],
        );
    }

    /**
     * 選択された値を、カラムに入れる値へ変換する。
     */
    public function castValue(string $value): string|bool
    {
        if ($this->name === 'is_active') {
            return $value === '1';
        }

        return $value;
    }
}
