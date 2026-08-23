<?php

namespace App\Tables;

use App\Models\BaseModel;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 「コード + 名称」だけを持つサブマスタ(部署 / 役職 / 商品分類)の共通一覧定義。
 *
 *   new SimpleMasterTable(Department::class, 'departments', 'masters.departments', '部署コード', '部署名');
 */
class SimpleMasterTable extends TableDefinition
{
    /**
     * @param  class-string<BaseModel>  $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly string $key,
        private readonly string $routeName,
        private readonly string $codeLabel,
        private readonly string $nameLabel,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function routeName(): string
    {
        return $this->routeName;
    }

    public function query(): Builder
    {
        return $this->modelClass::query();
    }

    public function columns(): array
    {
        return [
            new Column('code', $this->codeLabel, sortable: true, wrap: false),
            new Column('name', $this->nameLabel, sortable: true),
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
        return $this->codeLabel.'・'.$this->nameLabel.'で検索';
    }

    public function filters(): array
    {
        return [Filter::activeFlag()];
    }

    public function toCsvRow(Model $model): array
    {
        /** @var BaseModel $model */
        return [
            $model->getAttribute('code'),
            $model->getAttribute('name'),
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }
}
