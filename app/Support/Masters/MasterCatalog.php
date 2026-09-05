<?php

namespace App\Support\Masters;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * マスタ管理ハブに並べるカードの一覧。
 *
 * 業務システムごとにマスタが増える場合は、このクラスを継承して cards() を足し、
 * サービスプロバイダでコンテナに差し込む(NavigationMenu と同じ流儀)。
 *
 *   // 例: CRM 側
 *   $this->app->bind(MasterCatalog::class, CrmMasterCatalog::class);
 */
class MasterCatalog
{
    /**
     * @return list<MasterCard>
     */
    public function cards(): array
    {
        return [
            new MasterCard(
                key: 'organizations',
                label: '組織',
                description: '地域 > エリア > 店舗 の階層。社員の所属先で、売上などの集計単位になります。',
                icon: 'departments',
                routeName: 'masters.organizations',
                modelClass: Organization::class,
            ),
            new MasterCard(
                key: 'employees',
                label: '社員',
                description: '自社の社員。担当者の割り当てや、ログインユーザーとの紐付けに使います。',
                icon: 'employees',
                routeName: 'masters.employees',
                modelClass: Employee::class,
            ),
            new MasterCard(
                key: 'partners',
                label: '取引先',
                description: '得意先・仕入先の会社。売上や仕入の相手先として参照します。',
                icon: 'partners',
                routeName: 'masters.partners',
                modelClass: Partner::class,
            ),
            new MasterCard(
                key: 'products',
                label: '商品',
                description: '販売する商品・サービス。単価と分類を持ちます。',
                icon: 'products',
                routeName: 'masters.products',
                modelClass: Product::class,
            ),
            new MasterCard(
                key: 'departments',
                label: '部署',
                description: '社員が所属する部署。社員マスタのサブマスタです。',
                icon: 'departments',
                routeName: 'masters.departments',
                modelClass: Department::class,
            ),
            new MasterCard(
                key: 'positions',
                label: '役職',
                description: '社員の役職。社員マスタのサブマスタです。',
                icon: 'positions',
                routeName: 'masters.positions',
                modelClass: Position::class,
            ),
            new MasterCard(
                key: 'product-categories',
                label: '商品分類',
                description: '商品のグルーピング。商品マスタのサブマスタです。',
                icon: 'categories',
                routeName: 'masters.product-categories',
                modelClass: ProductCategory::class,
            ),
        ];
    }

    /**
     * ルートが登録されているカードだけを返す。
     *
     * @return list<MasterCard>
     */
    public function availableCards(): array
    {
        return array_values(array_filter(
            $this->cards(),
            static fn (MasterCard $card): bool => Route::has($card->routeName.'.index'),
        ));
    }

    /**
     * このユーザーが開けるカードだけを返す。
     *
     * マスタごとに必要な権限が違う場合(管理者だけが扱うマスタなど)に、
     * 開けない入口をハブに出さないための絞り込み。
     *
     * @return list<MasterCard>
     */
    public function visibleCards(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            $this->availableCards(),
            static fn (MasterCard $card): bool => $user->can($card->viewPermission()->value),
        ));
    }

    /**
     * 各マスタの件数。マスタごとにクエリを投げず、1 クエリでまとめて数える。
     *
     * @return array<string, int> [key => 件数]
     */
    public function counts(): array
    {
        $cards = $this->availableCards();

        if ($cards === []) {
            return [];
        }

        $query = DB::query();

        foreach ($cards as $card) {
            // 論理削除された行は数えない(モデルのグローバルスコープがそのまま効く)
            $query->selectSub(
                $card->modelClass::query()->toBase()->selectRaw('count(*)'),
                $card->key,
            );
        }

        // 集計だけのクエリなので、行は必ず 1 件返る
        $row = $query->first();
        assert($row !== null);

        $counts = [];

        foreach ($cards as $card) {
            $counts[$card->key] = (int) $row->{$card->key};
        }

        return $counts;
    }
}
