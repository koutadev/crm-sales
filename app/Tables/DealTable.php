<?php

namespace App\Tables;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Partner;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 商談一覧の定義。
 *
 * 金額は商談に保存済みの税込合計(amount_total)をそのまま並べる。
 * CSV には画面に出さない消費税・税抜も足すため、明細を eager load しておく
 * (明細から税率ごとの内訳を組み立てるのに必要)。
 */
class DealTable extends TableDefinition
{
    public function key(): string
    {
        return 'deals';
    }

    public function routeName(): string
    {
        return 'deals';
    }

    public function query(): Builder
    {
        return Deal::query()->with([
            'partner:id,code,name',
            'employee:id,name',
            'items:id,deal_id,amount_incl_tax,tax_rate_percent',
        ]);
    }

    public function columns(): array
    {
        return [
            new Column('code', '商談コード', sortable: true, wrap: false),
            new Column('partner_id', '顧客'),
            new Column('title', '件名', sortable: true),
            new Column('status', 'ステータス', sortable: true, align: 'center'),
            new Column('probability', '確度', sortable: true, align: 'right', wrap: false),
            new Column('amount_total', '金額(税込)', sortable: true, align: 'right', wrap: false),
            new Column('expected_close_date', '予定クローズ日', sortable: true, wrap: false),
            new Column('employee_id', '営業担当'),
        ];
    }

    /**
     * CSV には金額の内訳(消費税・税抜)も出す。
     */
    public function csvColumns(): array
    {
        $columns = $this->columns();

        array_splice($columns, 6, 0, [
            new Column('tax_amount', '消費税', align: 'right'),
            new Column('amount_excl_tax', '税抜', align: 'right'),
        ]);

        $columns[] = new Column('ordered_at', '受注日');

        return $columns;
    }

    public function searchable(): array
    {
        return ['code', 'title'];
    }

    public function searchPlaceholder(): string
    {
        return '商談コード・件名で検索';
    }

    public function filters(): array
    {
        return [
            new Filter('status', 'ステータス', DealStatus::options()),
            new Filter('partner_id', '顧客', $this->customerOptions()),
            new Filter('employee_id', '営業担当', $this->employeeOptions()),
        ];
    }

    public function defaultSort(): string
    {
        return 'expected_close_date';
    }

    public function toCsvRow(Model $model): array
    {
        /** @var Deal $model */
        $summary = $model->amountSummary();

        return [
            $model->code,
            $model->partner?->name,
            $model->title,
            $model->status->label(),
            $model->probability.'%',
            $model->amount_total,
            $summary->totalTax(),
            $summary->totalExclTax(),
            $model->expected_close_date->format('Y/m/d'),
            $model->employee?->name,
            $model->ordered_at?->format('Y/m/d'),
        ];
    }

    /**
     * @return array<array-key, string>
     */
    private function customerOptions(): array
    {
        return Partner::query()->customers()->orderBy('code')->pluck('name', 'id')->all();
    }

    /**
     * @return array<array-key, string>
     */
    private function employeeOptions(): array
    {
        return Employee::query()->active()->orderBy('code')->pluck('name', 'id')->all();
    }
}
