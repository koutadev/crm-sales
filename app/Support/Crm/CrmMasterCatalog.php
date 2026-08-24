<?php

namespace App\Support\Crm;

use App\Models\TaxRate;
use App\Support\Masters\MasterCard;
use App\Support\Masters\MasterCatalog;

/**
 * CRM のマスタ管理ハブ。
 *
 * 共通基盤のマスタに、CRM 固有の税率マスタを足す。
 */
class CrmMasterCatalog extends MasterCatalog
{
    /**
     * @return list<MasterCard>
     */
    public function cards(): array
    {
        return array_merge([
            new MasterCard(
                key: 'tax_rates',
                label: '税率',
                description: '消費税の税率。適用開始日で世代管理し、商品の標準税率に使います。',
                icon: 'categories',
                routeName: 'masters.tax-rates',
                modelClass: TaxRate::class,
            ),
        ], parent::cards());
    }
}
