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
     * @param  bool  $combobox  セレクトではなくコンボボックス(入力で絞る)で表示するか
     * @param  string|null  $source  非同期モードのエンドポイント(候補が多いマスタ向け。指定するとコンボボックス扱い)
     * @param  (\Closure(string): ?string)|null  $labelResolver  非同期モードで、選択中の値のラベルを引く手段
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly array $options,
        public readonly ?string $column = null,
        public readonly string $placeholder = 'すべて',
        public readonly array $valueGroups = [],
        public readonly bool $combobox = false,
        public readonly ?string $source = null,
        private readonly ?\Closure $labelResolver = null,
    ) {}

    /**
     * 入力で候補を絞るコンボボックスで表示する条件か。
     */
    public function isCombobox(): bool
    {
        return $this->combobox || $this->source !== null;
    }

    /**
     * この絞り込みが受け付けられる値か(URL 直打ち対策)。
     *
     * 候補を持っている場合はその中の値だけ。
     * 非同期モード(候補をサーバ側に置く)は手元に候補がないので、ID(数字)だけを受け付ける。
     */
    public function accepts(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if ($this->options !== []) {
            return array_key_exists($value, $this->options);
        }

        return $this->source !== null && ctype_digit($value);
    }

    /**
     * 選択中の値に対応するラベル。
     *
     * 非同期モードでは候補を持っていないので、絞り込み欄に選択中の名前を出すために使う。
     */
    public function labelFor(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (array_key_exists($value, $this->options)) {
            return (string) $this->options[$value];
        }

        if ($this->labelResolver !== null) {
            return (string) (($this->labelResolver)($value) ?? '');
        }

        return '';
    }

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
