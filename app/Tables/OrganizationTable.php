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
            // 既定の並び(階層順)はこの列のキーで表す
            new Column('hierarchy', '組織名', sortable: true),
            new Column('prefecture', '都道府県', sortable: true, wrap: false),
            new Column('parent_id', '上位組織'),
            new Column('is_active', '状態', sortable: true, align: 'center'),
            new Column('updated_at', '更新日時', sortable: true, wrap: false),
        ];
    }

    public function searchable(): array
    {
        // 階層順に並べるとき親を join するので、列名は必ずテーブル名で修飾する
        return ['organizations.code', 'organizations.name'];
    }

    public function searchPlaceholder(): string
    {
        return '組織コード・組織名で検索';
    }

    public function filters(): array
    {
        return [
            new Filter('type', '種別', OrganizationType::options(), column: 'organizations.type'),
            new Filter('parent_id', '上位組織', $this->parentOptions(), column: 'organizations.parent_id'),
            new Filter('prefecture', '都道府県', $this->prefectureOptions(), column: 'organizations.prefecture'),
            new Filter('is_active', '状態', ['1' => '有効', '0' => '無効'], column: 'organizations.is_active'),
        ];
    }

    /**
     * 既定は階層順（地域 → その配下のエリア → その配下の店舗）。
     */
    public function defaultSort(): string
    {
        return 'hierarchy';
    }

    public function defaultDirection(): string
    {
        return 'asc';
    }

    /**
     * 階層順の並び。
     *
     * 3 段しかないので、親を 2 回 left join して
     * 「地域のコード → エリアのコード → 店舗のコード」の順に並べる。
     * 再帰は使わず、クエリも 1 本のまま。
     */
    public function applySort(Builder $query, string $sort, string $direction): bool
    {
        if ($sort !== 'hierarchy') {
            return false;
        }

        $region = OrganizationType::Region->value;
        $area = OrganizationType::Area->value;
        $store = OrganizationType::Store->value;

        $query
            ->leftJoin('organizations as parent1', 'parent1.id', '=', 'organizations.parent_id')
            ->leftJoin('organizations as parent2', 'parent2.id', '=', 'parent1.parent_id')
            ->select('organizations.*')
            ->orderByRaw(
                "case organizations.type when ? then organizations.code when ? then parent1.code else parent2.code end $direction",
                [$region, $area]
            )
            ->orderByRaw(
                "case organizations.type when ? then '' when ? then organizations.code else parent1.code end $direction",
                [$region, $area]
            )
            ->orderByRaw(
                "case organizations.type when ? then organizations.code else '' end $direction",
                [$store]
            );

        return true;
    }

    public function toCsvRow(Model $model): array
    {
        /** @var Organization $model */
        return [
            $model->code,
            $model->type->label(),
            $model->path(),
            $model->prefecture,
            $model->parent?->name,
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * 登録済みの都道府県だけを選択肢にする。
     *
     * @return array<array-key, string>
     */
    private function prefectureOptions(): array
    {
        return $this->cachedOptions(
            'organization-prefectures',
            static fn (): array => Organization::query()
                ->whereNotNull('prefecture')
                ->distinct()
                ->orderBy('prefecture')
                ->pluck('prefecture', 'prefecture')
                ->all(),
        );
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
