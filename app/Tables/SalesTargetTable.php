<?php

namespace App\Tables;

use App\Enums\TargetScope;
use App\Models\SalesTarget;
use App\Support\Crm\TargetLabels;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 売上目標一覧の定義。
 *
 * 既定は新しい年月から。対象名（組織 / 社員）は粒度で参照先が変わるため、
 * 表示のたびに引かず TargetLabels でまとめて解決する。
 */
class SalesTargetTable extends TableDefinition
{
    public function key(): string
    {
        return 'sales-targets';
    }

    public function routeName(): string
    {
        return 'masters.sales-targets';
    }

    public function query(): Builder
    {
        return SalesTarget::query();
    }

    public function columns(): array
    {
        return [
            new Column('code', '目標コード', sortable: true, wrap: false),
            new Column('period', '対象期間', sortable: true, wrap: false),
            new Column('scope', '粒度', sortable: true, align: 'center', wrap: false),
            new Column('target_id', '対象'),
            new Column('amount', '目標金額(税込)', sortable: true, align: 'right', wrap: false),
            new Column('is_active', '状態', sortable: true, align: 'center'),
            new Column('updated_at', '更新日時', sortable: true, wrap: false),
        ];
    }

    public function searchable(): array
    {
        return ['code'];
    }

    public function searchPlaceholder(): string
    {
        return '目標コードで検索';
    }

    public function filters(): array
    {
        return [
            new Filter('scope', '粒度', TargetScope::options()),
            new Filter('year', '年', $this->yearOptions()),
            new Filter('month', '月', $this->monthOptions()),
            Filter::activeFlag(),
        ];
    }

    public function defaultSort(): string
    {
        return 'period';
    }

    public function defaultDirection(): string
    {
        return 'desc';
    }

    /**
     * 対象期間は「年 → 月」の 2 段で並べる。
     */
    public function applySort(Builder $query, string $sort, string $direction): bool
    {
        if ($sort !== 'period') {
            return false;
        }

        $query->orderBy('year', $direction)
            ->orderBy('month', $direction)
            ->orderBy('scope')
            ->orderBy('target_id');

        return true;
    }

    public function toCsvRow(Model $model): array
    {
        /** @var SalesTarget $model */
        return [
            $model->code,
            $model->periodLabel(),
            $model->scope->label(),
            TargetLabels::for([$model])->of($model),
            $model->amount,
            $model->activeLabel(),
            $model->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * 登録済みの年。
     *
     * @return array<array-key, string>
     */
    private function yearOptions(): array
    {
        return $this->cachedOptions(
            'sales-target-years',
            static fn (): array => SalesTarget::query()
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year', 'year')
                ->map(static fn (int $year): string => $year.'年')
                ->all(),
        );
    }

    /**
     * @return array<array-key, string>
     */
    private function monthOptions(): array
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = $month.'月';
        }

        return $months;
    }
}
