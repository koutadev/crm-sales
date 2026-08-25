<?php

namespace App\Support\DataTable;

use App\Models\SavedView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * ビューに渡す一覧オブジェクト。
 *
 * 定義(TableDefinition)・表示条件(TableState)・ページング結果をまとめ、
 * 並び替えリンクや CSV リンクの URL 生成を担当する。
 *
 *   $table = Table::make(new EmployeeTable, $request, $user->isAdmin());
 *   return view('masters.employees.index', ['table' => $table]);
 */
class Table
{
    /**
     * @param  LengthAwarePaginator<int, Model>  $paginator
     */
    /** @var Collection<int, SavedView>|null 保存ビュー(描画時に一度だけ読む) */
    private ?Collection $savedViews = null;

    public function __construct(
        public readonly TableDefinition $definition,
        public readonly TableState $state,
        public readonly LengthAwarePaginator $paginator,
        public readonly bool $canViewTrashed,
    ) {}

    public static function make(TableDefinition $definition, Request $request, bool $canViewTrashed = false): self
    {
        $state = TableState::resolve($request, $definition, $canViewTrashed);

        $paginator = (new TableBuilder($definition, $state))->paginate();

        return new self($definition, $state, $paginator, $canViewTrashed);
    }

    /**
     * @return list<Column>
     */
    public function columns(): array
    {
        return $this->definition->columns();
    }

    /**
     * この一覧の保存ビュー(自分のぶんだけ)。
     *
     * 使わない一覧では 1 本もクエリを投げない。
     *
     * @return Collection<int, SavedView>
     */
    public function savedViews(): Collection
    {
        if (! $this->definition->savedViews()) {
            return collect();
        }

        return $this->savedViews ??= SavedView::forTable(request()->user()?->id, $this->definition->key());
    }

    /**
     * 適用中の保存ビュー。
     */
    public function activeView(): ?SavedView
    {
        if ($this->state->view === '') {
            return null;
        }

        return $this->savedViews()->firstWhere('id', (int) $this->state->view);
    }

    /**
     * 保存ビューを呼び出す URL。
     */
    public function viewUrl(SavedView $view): string
    {
        return route($this->definition->routeName().'.index', ['view' => $view->id]);
    }

    /**
     * @return list<Filter>
     */
    public function filters(): array
    {
        return $this->definition->filters();
    }

    /**
     * @return Collection<int, Model>
     */
    public function items(): Collection
    {
        return collect($this->paginator->items());
    }

    public function isEmpty(): bool
    {
        return $this->paginator->total() === 0;
    }

    public function indexUrl(): string
    {
        return route($this->definition->routeName().'.index');
    }

    /**
     * 見出しクリック時の並び替え URL。同じ列なら昇順 / 降順を切り替える。
     */
    public function sortUrl(Column $column): string
    {
        $direction = $this->isSortedBy($column) && $this->state->direction === 'asc' ? 'desc' : 'asc';

        return $this->urlWith(['sort' => $column->key, 'direction' => $direction]);
    }

    public function isSortedBy(Column $column): bool
    {
        return $this->state->sort === $column->key;
    }

    /**
     * 並び替えの向きを示す記号。
     */
    public function sortIndicator(Column $column): string
    {
        if (! $this->isSortedBy($column)) {
            return '';
        }

        return $this->state->direction === 'asc' ? '▲' : '▼';
    }

    public function exportUrl(): string
    {
        return route($this->definition->routeName().'.export', $this->state->toQuery());
    }

    public function resetUrl(): string
    {
        return route($this->definition->routeName().'.index', ['reset' => 1]);
    }

    /**
     * 削除済みの表示切り替え URL('' / 'with' / 'only')。
     */
    public function trashedUrl(string $mode): string
    {
        $query = $this->state->toQuery();
        unset($query['trashed']);

        if ($mode !== '') {
            $query['trashed'] = $mode;
        }

        return route($this->definition->routeName().'.index', $query);
    }

    /**
     * 現在の条件に指定パラメータを上書きした URL を作る。
     *
     * @param  array<string, string>  $overrides
     */
    private function urlWith(array $overrides): string
    {
        return route(
            $this->definition->routeName().'.index',
            array_merge($this->state->toQuery(), $overrides),
        );
    }
}
