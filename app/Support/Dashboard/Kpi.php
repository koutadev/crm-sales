<?php

namespace App\Support\Dashboard;

/**
 * ダッシュボード上部の KPI カード 1 枚ぶん。
 *
 * 各システムでは、このオブジェクトを作って view に渡すだけで
 * カードの並びを差し替えられる。
 */
class Kpi
{
    /**
     * @param  string  $label  見出し（例: 社員）
     * @param  int|string  $value  数値本体（例: 32）
     * @param  string  $unit  単位（例: 件）
     * @param  string|null  $href  カードを押したときの遷移先。null ならリンクにしない
     * @param  string|null  $note  補足（例: うち在籍 28 名）
     */
    public function __construct(
        public readonly string $label,
        public readonly int|string $value,
        public readonly string $unit = '',
        public readonly ?string $href = null,
        public readonly ?string $note = null,
    ) {}

    public function formattedValue(): string
    {
        return is_int($this->value) ? number_format($this->value) : $this->value;
    }
}
