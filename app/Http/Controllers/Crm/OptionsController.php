<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * コンボボックス(非同期モード)の候補を返すエンドポイント。
 *
 * 候補が多いマスタでは、全件を画面に埋め込む代わりにここへ問い合わせる。
 * 返す形は共通部品が期待する [{ value, label }]。
 *
 * かな入力への対応：候補側の表記に依存する。
 * 商品名のようにカタカナを含むものは「こーぽれーと」でも当たるが、
 * 漢字表記の会社名・人名にかなで当てるには読み(カナ)の列が必要になる。
 */
class OptionsController extends Controller
{
    /** 1 回に返す候補の数。 */
    private const LIMIT = 30;

    public function customers(Request $request): JsonResponse
    {
        return $this->respond(Partner::query()->customers(), $request);
    }

    public function employees(Request $request): JsonResponse
    {
        return $this->respond(Employee::query()->active(), $request);
    }

    public function products(Request $request): JsonResponse
    {
        return $this->respond(Product::query()->active(), $request);
    }

    /**
     * コード・名称の部分一致で候補を返す。
     *
     * @param  Builder<covariant Model>  $query
     */
    private function respond(Builder $query, Request $request): JsonResponse
    {
        $keyword = trim((string) $request->string('q'));

        $options = $query
            ->when($keyword !== '', function (Builder $builder) use ($keyword): void {
                // 検索文字のワイルドカードはエスケープしてそのまま探す
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword).'%';

                // PostgreSQL では大文字小文字を区別しない ILIKE を使う
                $operator = $builder->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

                $builder->where(function (Builder $sub) use ($like, $operator): void {
                    $sub->orWhere('name', $operator, $like)
                        ->orWhere('code', $operator, $like);
                });
            })
            ->orderBy('code')
            ->limit(self::LIMIT)
            ->pluck('name', 'id');

        return response()->json(
            $options->map(fn (string $name, int $id): array => [
                'value' => (string) $id,
                'label' => $name,
            ])->values()->all()
        );
    }
}
