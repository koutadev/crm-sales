<?php

namespace App\Support\Crm;

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
    ) {}

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
