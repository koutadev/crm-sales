<?php

namespace App\Support\DataTable;

use App\Models\SavedView;
use Illuminate\Http\Request;

/**
 * 保存ビュー(マイビュー)の条件を、一覧のリクエストに流し込む。
 *
 * 呼び出しは一覧に ?view=<id> を付けるだけ。
 * ここでビューの条件をリクエストへ足すので、あとは通常のリクエストと
 * まったく同じ経路(TableState)を通り、サマリも CSV も同じ条件で動く。
 *
 * 他人のビュー ID を URL に書いても、自分のビューでなければ無視される。
 */
class SavedViewConditions
{
    /** 保存できる条件の数と長さの上限(壊れた入力を持ち込ませないための保険)。 */
    private const MAX_KEYS = 30;

    private const MAX_LENGTH = 191;

    /**
     * ?view=<id> があれば、そのビューの条件をリクエストに足す。
     *
     * すでにリクエストにあるパラメータは上書きしない
     * (並び替えやページ送りのリンクは、ビューを保ったまま条件を変えられる)。
     */
    public static function mergeInto(Request $request, string $tableKey): void
    {
        $view = self::find($request, $tableKey, (string) $request->string('view'));

        if ($view === null) {
            // 存在しない / 他人のビューは、指定そのものをなかったことにする
            $request->merge(['view' => '']);

            return;
        }

        $request->merge(array_diff_key(self::sanitize($view->conditions), $request->all()));
    }

    /**
     * 条件もセッションの記憶もないときに使う既定ビューの条件。
     *
     * @return array<string, string>|null
     */
    public static function defaultParams(Request $request, string $tableKey): ?array
    {
        $view = SavedView::query()
            ->ownedBy($request->user()?->id)
            ->where('table_key', $tableKey)
            ->where('is_default', true)
            ->first();

        if ($view === null) {
            return null;
        }

        return self::sanitize($view->conditions) + ['view' => (string) $view->id];
    }

    /**
     * 保存する条件をそのまま信用せず、文字列・件数・長さを整える。
     *
     * @param  array<array-key, mixed>  $conditions
     * @return array<string, string>
     */
    public static function sanitize(array $conditions): array
    {
        $clean = [];

        foreach ($conditions as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            // ページ番号と view 自身は条件として持たない
            if (in_array($key, ['page', 'view', 'reset'], true)) {
                continue;
            }

            $value = (string) $value;

            if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
                continue;
            }

            $clean[$key] = $value;

            if (count($clean) >= self::MAX_KEYS) {
                break;
            }
        }

        return $clean;
    }

    private static function find(Request $request, string $tableKey, string $id): ?SavedView
    {
        if (! ctype_digit($id)) {
            return null;
        }

        return SavedView::query()
            ->ownedBy($request->user()?->id)
            ->where('table_key', $tableKey)
            ->find((int) $id);
    }
}
