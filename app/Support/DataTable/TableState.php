<?php

namespace App\Support\DataTable;

use Illuminate\Http\Request;

/**
 * 一覧の表示条件(検索キーワード・絞り込み・並び順・ページ)。
 *
 * 条件はセッションに保存され、クエリパラメータを付けずに一覧を開き直したときに復元される。
 * これにより「詳細画面へ移動して戻ってきても検索条件が残る」挙動を実現している。
 * 条件を消したいときは ?reset=1 を付けてアクセスする。
 */
class TableState
{
    /**
     * @param  array<string, string>  $filters
     * @param  string  $direction  'asc' | 'desc'
     * @param  string  $trashed  '' (通常) | 'with' (削除済みも含む) | 'only' (削除済みのみ)
     */
    public function __construct(
        public readonly string $search = '',
        public readonly array $filters = [],
        public readonly string $sort = 'updated_at',
        public readonly string $direction = 'desc',
        public readonly string $trashed = '',
        public readonly int $page = 1,
    ) {}

    public static function sessionKey(TableDefinition $definition): string
    {
        return 'datatable.'.$definition->key();
    }

    /**
     * リクエストとセッションから表示条件を組み立てる。
     *
     * @param  bool  $canViewTrashed  削除済みを表示できるユーザーか(管理者のみ true)
     */
    public static function resolve(Request $request, TableDefinition $definition, bool $canViewTrashed): self
    {
        $sessionKey = self::sessionKey($definition);

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
            $params = [];
        } elseif (self::hasAnyParameter($request, $definition)) {
            $params = self::extract($request, $definition);
            $request->session()->put($sessionKey, $params);
        } else {
            /** @var array<string, mixed> $params */
            $params = $request->session()->get($sessionKey, []);
        }

        return self::fromParameters($params, $definition, $canViewTrashed);
    }

    /**
     * このリクエストが一覧の条件を指定しているか。
     */
    private static function hasAnyParameter(Request $request, TableDefinition $definition): bool
    {
        $keys = ['q', 'sort', 'direction', 'trashed', 'page'];

        foreach ($definition->filters() as $filter) {
            $keys[] = $filter->name;
        }

        foreach ($keys as $key) {
            if ($request->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function extract(Request $request, TableDefinition $definition): array
    {
        $params = [
            'q' => (string) $request->string('q'),
            'sort' => (string) $request->string('sort'),
            'direction' => (string) $request->string('direction'),
            'trashed' => (string) $request->string('trashed'),
            'page' => (int) $request->integer('page', 1),
        ];

        foreach ($definition->filters() as $filter) {
            $params[$filter->name] = (string) $request->string($filter->name);
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function fromParameters(array $params, TableDefinition $definition, bool $canViewTrashed): self
    {
        $filters = [];

        foreach ($definition->filters() as $filter) {
            $value = isset($params[$filter->name]) ? (string) $params[$filter->name] : '';

            // 定義にない値は無視する(URL 直打ち対策)
            if ($value !== '' && array_key_exists($value, $filter->options)) {
                $filters[$filter->name] = $value;
            }
        }

        $sort = isset($params['sort']) ? (string) $params['sort'] : '';
        if (! in_array($sort, $definition->sortableColumns(), true)) {
            $sort = $definition->defaultSort();
        }

        $direction = isset($params['direction']) ? strtolower((string) $params['direction']) : '';
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $definition->defaultDirection();
        }

        $trashed = isset($params['trashed']) ? (string) $params['trashed'] : '';
        if (! $canViewTrashed || ! in_array($trashed, ['with', 'only'], true)) {
            $trashed = '';
        }

        $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;

        return new self(
            search: trim((string) ($params['q'] ?? '')),
            filters: $filters,
            sort: $sort,
            direction: $direction,
            trashed: $trashed,
            page: $page,
        );
    }

    /**
     * ページ番号を除いた、リンクに引き継ぐクエリパラメータ。
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->search !== '') {
            $query['q'] = $this->search;
        }

        foreach ($this->filters as $name => $value) {
            $query[$name] = $value;
        }

        $query['sort'] = $this->sort;
        $query['direction'] = $this->direction;

        if ($this->trashed !== '') {
            $query['trashed'] = $this->trashed;
        }

        return $query;
    }

    public function filterValue(string $name): string
    {
        return $this->filters[$name] ?? '';
    }

    public function hasConditions(): bool
    {
        return $this->search !== '' || $this->filters !== [] || $this->trashed !== '';
    }
}
