<?php

namespace App\Tables;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 商品マスタ一覧の定義。
 */
class ProductTable extends TableDefinition
{
    public function key(): string
    {
        return 'products';
    }

    public function routeName(): string
    {
        return 'masters.products';
    }

    public function query(): Builder
    {
        return Product::query()->with([
            'category:id,name',
            'taxRate:id,name,rate_percent,effective_from',
        ]);
    }

    public function columns(): array
    {
        return [
            new Column('code', '商品コード', sortable: true, wrap: false),
            new Column('name', '商品名', sortable: true),
            new Column('product_category_id', '分類'),
            new Column('unit_price', '標準単価(税込)', sortable: true, align: 'right', wrap: false),
            new Column('tax_rate_id', '税率', align: 'center', wrap: false),
            new Column('unit', '単位', align: 'center'),
            new Column('is_active', '状態', sortable: true, align: 'center'),
            new Column('updated_at', '更新日時', sortable: true, wrap: false),
        ];
    }

    public function searchable(): array
    {
        return ['code', 'name'];
    }

    public function searchPlaceholder(): string
    {
        return '商品コード・商品名で検索';
    }

    public function filters(): array
    {
        return [
            new Filter('product_category_id', '分類', $this->categoryOptions()),
            new Filter('tax_rate_id', '税率', $this->taxRateOptions()),
            Filter::activeFlag(),
        ];
    }

    public function toCsvRow(Model $model): array
    {
        /** @var Product $model */
        return [
            $model->code,
            $model->name,
            $model->category?->name,
            $model->unit_price,
            $model->taxRate?->label(),
            $model->unit,
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * @return array<array-key, string>
     */
    private function categoryOptions(): array
    {
        return ProductCategory::query()->orderBy('code')->pluck('name', 'id')->all();
    }

    /**
     * @return array<array-key, string>
     */
    private function taxRateOptions(): array
    {
        return TaxRate::options();
    }
}
