<?php

namespace App\Tables;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 組織一覧の定義。
 *
 * 並びは「地域 > エリア > 店舗」が分かる順（上位から、同じ階層はコード順）を既定にする。
 * 上位 2 段ぶんの親を eager load しておき、階層の表示で N+1 を出さない。
 */
class OrganizationTable extends TableDefinition
{
    public function key(): string
    {
        return 'organizations';
    }

    public function routeName(): string
    {
        return 'masters.organizations';
    }

    public function query(): Builder
    {
        return Organization::query()->with(['parent:id,name,parent_id', 'parent.parent:id,name']);
    }

    public function columns(): array
    {
        return [
            new Column('code', '組織コード', sortable: true, wrap: false),
            new Column('type', '種別', sortable: true, align: 'center', wrap: false),
            new Column('name', '組織名', sortable: true),
            new Column('parent_id', '上位組織'),
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
        return '組織コード・組織名で検索';
    }

    public function filters(): array
    {
        return [
            new Filter('type', '種別', OrganizationType::options()),
            new Filter('parent_id', '上位組織', $this->parentOptions()),
            Filter::activeFlag(),
        ];
    }

    /**
     * 既定は「種別（地域 → エリア → 店舗）」順。
     */
    public function defaultSort(): string
    {
        return 'type';
    }

    public function defaultDirection(): string
    {
        return 'asc';
    }

    public function toCsvRow(Model $model): array
    {
        /** @var Organization $model */
        return [
            $model->code,
            $model->type->label(),
            $model->name,
            $model->parent?->name,
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * 上位組織になれるもの（地域とエリア）。
     *
     * @return array<array-key, string>
     */
    private function parentOptions(): array
    {
        return $this->cachedOptions(
            'organization-parents',
            static fn (): array => Organization::query()
                ->whereIn('type', [OrganizationType::Region->value, OrganizationType::Area->value])
                ->orderBy('type')
                ->orderBy('code')
                ->pluck('name', 'id')
                ->all(),
        );
    }
}
