<?php

namespace App\Support\DataTable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 一覧画面 1 つぶんの定義。
 *
 * 各マスタはこのクラスを継承して「どのデータを・どの列で・何で検索/絞り込みできるか」だけを書く。
 * 検索条件の保持・ページング・ソート・CSV 出力は共通基盤側が面倒を見る。
 *
 * @see TableBuilder
 */
abstract class TableDefinition
{
    /**
     * 絞り込みの選択肢のキャッシュ。
     *
     * @var array<string, array<array-key, string>>
     */
    private array $optionCache = [];

    /**
     * 絞り込みの選択肢を、この定義インスタンスの中で 1 回だけ取得する。
     *
     * filters() は「表示条件の解決」「画面の描画」「クエリへの適用」で複数回呼ばれるため、
     * 素直にマスタを引くと同じ選択肢のクエリが何度も飛ぶ。
     *
     *   return [new Filter('employee_id', '担当', $this->cachedOptions('employees', fn () => …))];
     *
     * @param  callable(): array<array-key, string>  $resolver
     * @return array<array-key, string>
     */
    protected function cachedOptions(string $key, callable $resolver): array
    {
        return $this->optionCache[$key] ??= $resolver();
    }

    /**
     * 一覧の識別子。検索条件をセッションに保存するキーにも使う。
     */
    abstract public function key(): string;

    /**
     * ルート名のプレフィックス(例: 'masters.employees')。
     * 一覧は {prefix}.index、CSV は {prefix}.export を使う。
     */
    abstract public function routeName(): string;

    /**
     * 一覧のもとになるクエリ。
     *
     * @return Builder<covariant Model>
     */
    abstract public function query(): Builder;

    /**
     * 表示する列。
     *
     * @return list<Column>
     */
    abstract public function columns(): array;

    /**
     * キーワード検索の対象カラム(部分一致・大文字小文字を区別しない)。
     *
     * @return list<string>
     */
    abstract public function searchable(): array;

    /**
     * CSV の 1 行。columns() と同じ並びにすること。
     *
     * @return list<string|int|float|null>
     */
    abstract public function toCsvRow(Model $model): array;

    /**
     * 絞り込み条件。
     *
     * @return list<Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * 絞り込み条件のうち、セレクト以外で送るパラメータ名。
     *
     * 期間フィルタのように複数の入力をまとめて送るものは、ここに名前を並べると
     * 他の絞り込みと同じように前回の状態が保持され、並び替えやページ送りにも引き継がれる。
     *
     * @return list<string>
     */
    public function statefulParameters(): array
    {
        return [];
    }

    /**
     * 追加パラメータ(statefulParameters)による絞り込み。
     *
     * 期間フィルタのようにセレクト 1 つでは表せない条件は、ここでクエリに反映する。
     * 一覧・CSV・サマリはいずれも同じクエリを通るため、書くのは 1 か所でよい。
     *
     * @param  Builder<covariant Model>  $query
     */
    public function applyExtraFilters(Builder $query, TableState $state): void
    {
        //
    }

    /**
     * 保存ビュー(マイビュー)を使える一覧か。
     *
     * 有効にすると、絞り込みの組み合わせに名前を付けて保存し、
     * プルダウンから呼び出せるようになる(ユーザーごと)。
     */
    public function savedViews(): bool
    {
        return false;
    }

    /**
     * キーワード検索欄のプレースホルダ。
     */
    public function searchPlaceholder(): string
    {
        return 'コード・名称で検索';
    }

    public function defaultSort(): string
    {
        return 'updated_at';
    }

    /**
     * @return 'asc'|'desc'
     */
    public function defaultDirection(): string
    {
        return 'desc';
    }

    public function perPage(): int
    {
        return 20;
    }

    /**
     * CSV に出す列。既定は画面と同じ並び。
     *
     * 画面には出さない内訳(金額の内訳など)を CSV にだけ足したいときに上書きする。
     * 上書きした場合は toCsvRow() の並びもこちらに合わせること。
     *
     * @return list<Column>
     */
    public function csvColumns(): array
    {
        return $this->columns();
    }

    /**
     * CSV 出力を提供するか。false にすると一覧から CSV ボタンが消える。
     */
    public function exportable(): bool
    {
        return true;
    }

    /**
     * CSV ファイル名(拡張子なし)。
     */
    public function exportFileName(): string
    {
        return $this->key();
    }

    /**
     * 並び替え可能なカラム名の一覧。
     *
     * @return list<string>
     */
    public function sortableColumns(): array
    {
        return array_values(array_map(
            static fn (Column $column): string => $column->key,
            array_filter($this->columns(), static fn (Column $column): bool => $column->sortable),
        ));
    }
}
