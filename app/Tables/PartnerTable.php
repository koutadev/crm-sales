<?php

namespace App\Tables;

use App\Enums\EntityType;
use App\Enums\PartnerType;
use App\Models\Partner;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 取引先マスタ一覧の定義。
 */
class PartnerTable extends TableDefinition
{
    public function key(): string
    {
        return 'partners';
    }

    public function routeName(): string
    {
        return 'masters.partners';
    }

    public function query(): Builder
    {
        return Partner::query();
    }

    public function columns(): array
    {
        return [
            new Column('code', '取引先コード', sortable: true, wrap: false),
            new Column('name', '取引先名', sortable: true),
            new Column('partner_type', '区分', sortable: true, align: 'center'),
            new Column('entity_type', '法人/個人', sortable: true, align: 'center'),
            new Column('phone', '電話番号', wrap: false),
            new Column('email', 'メール'),
            new Column('is_active', '状態', sortable: true, align: 'center'),
            new Column('updated_at', '更新日時', sortable: true, wrap: false),
        ];
    }

    public function searchable(): array
    {
        return ['code', 'name', 'email', 'phone', 'address'];
    }

    public function searchPlaceholder(): string
    {
        return '取引先コード・名称・連絡先で検索';
    }

    public function filters(): array
    {
        return [
            new Filter('partner_type', '取引先区分', PartnerType::options()),
            new Filter('entity_type', '法人/個人', EntityType::options()),
            Filter::activeFlag(),
        ];
    }

    public function toCsvRow(Model $model): array
    {
        /** @var Partner $model */
        return [
            $model->code,
            $model->name,
            $model->partner_type->label(),
            $model->entity_type->label(),
            $model->phone,
            $model->email,
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }
}
