<?php

namespace App\Support\DataTable;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\LazyCollection;

/**
 * 一覧の表示条件をクエリに適用する共通ロジック。
 *
 * 各マスタは TableDefinition を書くだけでよく、検索・絞り込み・ソート・
 * 削除済みの扱い・ページングはすべてここに集約されている。
 */
class TableBuilder
{
    public function __construct(
        private readonly TableDefinition $definition,
        private readonly TableState $state,
    ) {}

    /**
     * ページングした結果を返す。
     *
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->paginate(
                perPage: $this->definition->perPage(),
                page: $this->state->page,
            )
            ->withQueryString();
    }

    /**
     * CSV 出力用に、ページングせず全件を 1 件ずつ取り出す。
     *
     * @return LazyCollection<int, Model>
     */
    public function lazy(): LazyCollection
    {
        return $this->filteredQuery()->lazy();
    }

    /**
     * 検索・絞り込み・並び順を適用した(ページングしない)クエリ。
     *
     * 一覧の上部に「絞り込み結果の合計」を出すときなど、
     * 表示条件に連動した集計を 1 クエリで取りたい場合に使う。
     *
     * @return Builder<covariant Model>
     */
    public function filteredQuery(): Builder
    {
        return $this->apply($this->definition->query());
    }

    /**
     * 検索・絞り込み・削除済み・並び順を適用する。
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function apply(Builder $query): Builder
    {
        $this->applyTrashed($query);
        $this->applySearch($query);
        $this->applyFilters($query);
        $this->applySort($query);

        return $query;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyTrashed(Builder $query): void
    {
        // 既定(一般ユーザー)は削除済みを一切表示しない
        if ($this->state->trashed === '') {
            return;
        }

        $model = $query->getModel();

        // 論理削除を使っていないモデル(users など)では何もしない
        if (! method_exists($model, 'getQualifiedDeletedAtColumn')) {
            return;
        }

        // withTrashed() / onlyTrashed() と同じ処理を、
        // SoftDeletes を持たないモデルでも型安全に書ける形で行う
        $query->withoutGlobalScope(SoftDeletingScope::class);

        if ($this->state->trashed === 'only') {
            $query->whereNotNull($model->getQualifiedDeletedAtColumn());
        }
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function applySearch(Builder $query): void
    {
        $keyword = $this->state->search;

        if ($keyword === '' || $this->definition->searchable() === []) {
            return;
        }

        // ワイルドカード文字はエスケープして、入力そのものを部分一致で探す
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword).'%';

        // PostgreSQL では大文字小文字を区別しない ILIKE を使う
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query->where(function (Builder $sub) use ($like, $operator): void {
            foreach ($this->definition->searchable() as $column) {
                $sub->orWhere($column, $operator, $like);
            }
        });
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyFilters(Builder $query): void
    {
        foreach ($this->definition->filters() as $filter) {
            $value = $this->state->filterValue($filter->name);

            if ($value === '') {
                continue;
            }

            $values = $filter->valuesFor($value);

            if ($values !== null) {
                $query->whereIn($filter->column(), $values);

                continue;
            }

            $query->where($filter->column(), $filter->castValue($value));
        }
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function applySort(Builder $query): void
    {
        $query->orderBy($this->state->sort, $this->state->direction);

        // 同値のときの並びを安定させる
        if ($this->state->sort !== 'id') {
            $query->orderBy('id', 'desc');
        }
    }
}
