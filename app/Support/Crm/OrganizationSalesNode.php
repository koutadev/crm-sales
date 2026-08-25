<?php

namespace App\Support\Crm;

use App\Support\Ui\Achievement;

/**
 * 組織別売上ツリーの 1 ノード（地域・エリア・店舗・担当者）。
 *
 * 金額は配下をすべて足し上げた税込合計。
 */
class OrganizationSalesNode
{
    /**
     * @param  string  $key  画面での開閉に使う一意のキー
     * @param  string  $typeLabel  地域 / エリア / 店舗 / 担当者
     * @param  list<OrganizationSalesNode>  $children
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $typeLabel,
        public readonly int $depth,
        public readonly int $amountInclTax,
        public readonly int $dealCount,
        public readonly array $children = [],
        /** 当月の実績(税込)。予実の達成率に使う */
        public readonly int $monthAmount = 0,
        /** 当月の目標(税込)。0 なら未設定 */
        public readonly int $monthTarget = 0,
        /** 都道府県(店舗のみ)。都道府県別に束ねるときに使う */
        public readonly ?string $prefecture = null,
    ) {}

    /**
     * 当月の達成率。
     */
    public function achievement(): Achievement
    {
        return Achievement::of($this->monthAmount, $this->monthTarget);
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * 全体に対する構成比（%）。
     */
    public function share(int $total): float
    {
        return $total > 0 ? round($this->amountInclTax / $total * 100, 1) : 0.0;
    }
}
