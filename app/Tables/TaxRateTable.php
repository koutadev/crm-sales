<?php

namespace App\Tables;

use App\Models\TaxRate;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 税率マスタ一覧の定義。
 *
 * 世代管理のマスタなので、既定の並びは適用開始日の降順(新しい世代が上)。
 */
class TaxRateTable extends TableDefinition
{
    public function key(): string
    {
        return 'tax-rates';
    }

    public function routeName(): string
    {
        return 'masters.tax-rates';
    }

    public function query(): Builder
    {
        return TaxRate::query();
    }

    public function columns(): array
    {
        return [
            new Column('name', '税率名', sortable: true),
            new Column('rate_percent', '税率', sortable: true, align: 'right', wrap: false),
            new Column('effective_from', '適用開始日', sortable: true, wrap: false),
            new Column('is_active', '状態', sortable: true, align: 'center'),
            new Column('updated_at', '更新日時', sortable: true, wrap: false),
        ];
    }

    public function searchable(): array
    {
        return ['name'];
    }

    public function searchPlaceholder(): string
    {
        return '税率名で検索';
    }

    public function filters(): array
    {
        return [Filter::activeFlag()];
    }

    public function defaultSort(): string
    {
        return 'effective_from';
    }

    public function toCsvRow(Model $model): array
    {
        /** @var TaxRate $model */
        return [
            $model->name,
            $model->rate_percent.'%',
            $model->effective_from->format('Y/m/d'),
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }
}
