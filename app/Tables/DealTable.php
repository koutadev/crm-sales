<?php

namespace App\Tables;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Partner;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use App\Support\DataTable\TableState;
use App\Support\Ui\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 商談一覧の定義。
 *
 * 金額は商談に保存済みの税込合計(amount_total)をそのまま並べる。
 * CSV には画面に出さない消費税・税抜も足すため、明細を eager load しておく
 * (明細から税率ごとの内訳を組み立てるのに必要)。
 *
 * 期間の絞り込みは「予定クローズ日」「受注日」を切り替えられる。
 * 相対プリセット(今月・今四半期・今年度・過去 N 日)はアクセスした日を基準に
 * 毎回計算されるため、月をまたいでも指定し直す必要はない。
 */
class DealTable extends TableDefinition
{
    /**
     * 絞り込みの候補を画面に埋め込む上限。
     *
     * これを超えるマスタは、全件を埋め込む代わりにコンボボックスの非同期モード
     * (入力のたびにサーバへ問い合わせる)へ自動的に切り替える。
     */
    private const MAX_STATIC_OPTIONS = 100;

    /** 一覧の見せ方(表 / カンバン)。 */
    public const VIEW_MODES = [
        'table' => '一覧',
        'kanban' => 'カンバン',
    ];

    /** 確度の絞り込み(「この値以上」で絞る)。 */
    public const PROBABILITY_STEPS = [
        '90' => '90% 以上',
        '70' => '70% 以上',
        '50' => '50% 以上',
        '30' => '30% 以上',
        '10' => '10% 以上',
    ];

    /** 期間フィルタの基準日にできる列(既定は先頭)。 */
    public const BASIS_COLUMNS = [
        'expected_close_date' => '予定クローズ日',
        'ordered_at' => '受注日',
    ];

    public function key(): string
    {
        return 'deals';
    }

    public function routeName(): string
    {
        return 'deals';
    }

    /**
     * よく使う絞り込み(期間・顧客・営業担当・ステータスなど)の組み合わせを
     * 保存ビュー(マイビュー)として残せるようにする。
     */
    public function savedViews(): bool
    {
        return true;
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
            new Column('ordered_at', '受注日', sortable: true, wrap: false),
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
            new Filter(
                name: 'status',
                label: 'ステータス',
                // ダッシュボードの KPI から「進行中だけ」を開けるよう、まとめた選択肢も用意する
                options: ['open' => '進行中（受注・失注を除く）'] + DealStatus::options(),
                valueGroups: ['open' => DealStatus::openValues()],
            ),
            $this->customerFilter(),
            $this->employeeFilter(),
        ];
    }

    /**
     * 期間フィルタは入力が 4 つ(基準日・プリセット・開始日・終了日)なので、
     * セレクトの絞り込みとは別にパラメータ名を宣言して状態保持に載せる。
     */
    public function statefulParameters(): array
    {
        return ['period_basis', 'period_preset', 'period_from', 'period_to', 'probability_min', 'view_mode'];
    }

    public function applyExtraFilters(Builder $query, TableState $state): void
    {
        self::dateRangeFrom($state)->apply($query, self::basisColumn($state->extra('period_basis')));

        // 確度は「この値以上」で絞る
        $probability = $state->extra('probability_min');

        if (array_key_exists($probability, self::PROBABILITY_STEPS)) {
            $query->where('probability', '>=', (int) $probability);
        }
    }

    /**
     * 状態から期間を組み立てる(相対プリセットはここで現在日から計算される)。
     */
    public static function dateRangeFrom(TableState $state): DateRange
    {
        return DateRange::fromValues(
            $state->extra('period_preset'),
            $state->extra('period_from'),
            $state->extra('period_to'),
        );
    }

    /**
     * 見せ方(不正な値や未指定は表に寄せる)。
     */
    public static function viewMode(TableState $state): string
    {
        $mode = $state->extra('view_mode');

        return array_key_exists($mode, self::VIEW_MODES) ? $mode : 'table';
    }

    /**
     * 基準日の列名(不正な値や未指定は既定の予定クローズ日に寄せる)。
     */
    public static function basisColumn(string $value): string
    {
        return array_key_exists($value, self::BASIS_COLUMNS) ? $value : array_key_first(self::BASIS_COLUMNS);
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
            $model->ordered_at?->format('Y/m/d'),
            $model->employee?->name,
        ];
    }

    /**
     * 顧客での絞り込み(入力で候補を絞るコンボボックス)。
     */
    private function customerFilter(): Filter
    {
        $options = $this->cachedOptions(
            'customers',
            static fn (): array => Partner::query()->customers()
                ->orderBy('code')
                ->limit(self::MAX_STATIC_OPTIONS + 1)
                ->pluck('name', 'id')
                ->all(),
        );

        return $this->comboboxFilter(
            name: 'partner_id',
            label: '顧客',
            options: $options,
            source: route('options.customers'),
            labelResolver: static fn (string $id): ?string => Partner::query()->whereKey($id)->value('name'),
        );
    }

    /**
     * 営業担当での絞り込み(入力で候補を絞るコンボボックス)。
     */
    private function employeeFilter(): Filter
    {
        $options = $this->cachedOptions(
            'employees',
            static fn (): array => Employee::query()->active()
                ->orderBy('code')
                ->limit(self::MAX_STATIC_OPTIONS + 1)
                ->pluck('name', 'id')
                ->all(),
        );

        return $this->comboboxFilter(
            name: 'employee_id',
            label: '営業担当',
            options: $options,
            source: route('options.employees'),
            labelResolver: static fn (string $id): ?string => Employee::query()->whereKey($id)->value('name'),
        );
    }

    /**
     * 候補の件数を見て、静的モード(その場で絞る)か非同期モード(サーバへ問い合わせる)を選ぶ。
     *
     * 候補は上限 + 1 件だけ読んでいるので、件数を数えるためのクエリは増えない。
     *
     * @param  array<array-key, string>  $options
     * @param  \Closure(string): ?string  $labelResolver
     */
    private function comboboxFilter(string $name, string $label, array $options, string $source, \Closure $labelResolver): Filter
    {
        $async = count($options) > self::MAX_STATIC_OPTIONS;

        return new Filter(
            name: $name,
            label: $label,
            options: $async ? [] : $options,
            combobox: true,
            source: $async ? $source : null,
            // 非同期モードでは候補を持たないので、選択中の名前だけ引けるようにしておく
            labelResolver: $async
                ? static fn (string $id): ?string => ctype_digit($id) ? $labelResolver($id) : null
                : null,
        );
    }
}
