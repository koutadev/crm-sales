<?php

namespace App\Tables;

use App\Models\Deal;
use App\Models\Partner;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 顧客(会社)一覧の定義。
 *
 * 取引先マスタのうち「得意先」を顧客として扱い、商談の金額を並べて表示する。
 * 金額は顧客ごとにクエリを投げると N+1 になるため、相関サブクエリとして
 * 一覧本体のクエリに埋め込む(一覧 1 回のクエリで全件ぶんが取れる)。
 */
class CustomerTable extends TableDefinition
{
    public function key(): string
    {
        return 'customers';
    }

    public function routeName(): string
    {
        return 'customers';
    }

    public function query(): Builder
    {
        return Partner::query()
            ->select('partners.*')
            ->customers()
            ->withCount('deals')
            ->addSelect([
                'won_amount_total' => $this->amountSubQuery(fn (Builder $query): Builder => $query->won()),
                'open_amount_total' => $this->amountSubQuery(fn (Builder $query): Builder => $query->open()),
            ]);
    }

    public function columns(): array
    {
        return [
            new Column('code', '顧客コード', sortable: true, wrap: false),
            new Column('name', '顧客名', sortable: true),
            new Column('won_amount_total', '累計売上(税込)', sortable: true, align: 'right', wrap: false),
            new Column('open_amount_total', '進行中商談(税込)', sortable: true, align: 'right', wrap: false),
            new Column('deals_count', '商談数', sortable: true, align: 'right', wrap: false),
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
        return '顧客コード・顧客名で検索';
    }

    public function filters(): array
    {
        return [Filter::activeFlag()];
    }

    public function toCsvRow(Model $model): array
    {
        /** @var Partner $model */
        return [
            $model->code,
            $model->name,
            (int) $model->getAttribute('won_amount_total'),
            (int) $model->getAttribute('open_amount_total'),
            (int) $model->getAttribute('deals_count'),
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * 顧客 1 件ぶんの商談金額を求める相関サブクエリ。
     *
     * 商談が 1 件も無い顧客でも 0 が返るようにしておく
     * (NULL のままだと並び替えたときに先頭へ来てしまうため)。
     *
     * @param  callable(Builder<Deal>): Builder<Deal>  $status
     * @return Builder<Deal>
     */
    private function amountSubQuery(callable $status): Builder
    {
        return $status(
            Deal::query()
                ->selectRaw('coalesce(sum(amount_total), 0)')
                ->whereColumn('deals.partner_id', 'partners.id')
        );
    }
}
