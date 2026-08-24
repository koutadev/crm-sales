<?php

namespace Database\Seeders;

use App\Enums\PartnerType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 共通マスタのサンプルデータ(本番環境では実行しない)。
 *
 * 仮想クライアント「アルティス・ソリューションズ」(架空のシステム開発 / 広告 / 人材の
 * 法人営業会社)の世界観に合わせて、部署・商品・取引先を用意する。
 *
 * 商品は「税込単価」で持ち、標準 10% と軽減 8% を混ぜてある
 * (商談明細の税率別内訳が確認できるように)。
 */
class MasterSampleSeeder extends Seeder
{
    private const DEPARTMENTS = ['営業部', 'マーケティング部', 'システム開発部', '人材事業部', '管理部'];

    private const POSITIONS = ['執行役員', '部長', '課長', '主任', '担当'];

    private const CATEGORIES = ['システム開発', '広告・プロモーション', '人材サービス', '保守・運用', '物販・ノベルティ'];

    /** 商品カタログ: [分類, 商品名, 税込単価, 単位, 軽減税率か] */
    private const PRODUCTS = [
        ['システム開発', '業務システム開発（基本パッケージ）', 3300000, '式', false],
        ['システム開発', '業務システム開発（追加モジュール）', 880000, '式', false],
        ['システム開発', 'コーポレートサイト制作', 1650000, '式', false],
        ['システム開発', 'スマートフォンアプリ開発', 2750000, '式', false],
        ['システム開発', '要件定義コンサルティング', 550000, '人月', false],

        ['広告・プロモーション', 'リスティング広告 運用代行（月額）', 330000, '月', false],
        ['広告・プロモーション', 'SNS広告クリエイティブ制作', 198000, '式', false],
        ['広告・プロモーション', '展示会ブース設営一式', 1210000, '式', false],
        ['広告・プロモーション', '会社案内パンフレット制作', 264000, '式', false],

        ['人材サービス', 'エンジニア派遣（月額）', 990000, '人月', false],
        ['人材サービス', '採用支援（成功報酬）', 1540000, '件', false],
        ['人材サービス', '社内研修プログラム（半日）', 176000, '回', false],

        ['保守・運用', '保守サポート スタンダード（月額）', 55000, '月', false],
        ['保守・運用', '保守サポート プレミアム（月額）', 132000, '月', false],
        ['保守・運用', 'サーバ監視サービス（月額）', 49800, '月', false],
        ['保守・運用', 'SSL証明書（年間）', 12800, '年', false],

        ['物販・ノベルティ', 'オリジナルトートバッグ', 2200, '個', false],
        ['物販・ノベルティ', 'ノートPC レンタル（月額）', 27500, '台', false],
        ['物販・ノベルティ', 'ノベルティ菓子詰め合わせ', 1080, '個', true],
        ['物販・ノベルティ', '来客用ドリンク（1ケース）', 3240, '箱', true],
        ['物販・ノベルティ', 'カタログギフト（food）', 5400, '冊', true],
    ];

    /** 取引先の件数と区分の内訳 */
    private const PARTNER_PLAN = [
        [PartnerType::Customer, 34],
        [PartnerType::Both, 10],
        [PartnerType::Supplier, 11],
    ];

    public function run(): void
    {
        // 既にサンプルが入っている場合は二重登録しない
        if (Employee::query()->exists()) {
            return;
        }

        // 大量投入なので操作ログは止める(デモでは利用者自身の操作だけがログに残る)
        config(['activity_log.enabled' => false]);

        try {
            $departments = $this->createNamed(Department::class, self::DEPARTMENTS);
            $positions = $this->createNamed(Position::class, self::POSITIONS);
            $categories = $this->createNamed(ProductCategory::class, self::CATEGORIES);

            $this->createProducts($categories);
            $this->createEmployees($departments, $positions);
            $this->createPartners();
        } finally {
            config(['activity_log.enabled' => true]);
        }
    }

    /**
     * 「コード + 名称」だけのサブマスタをまとめて作る。
     *
     * @template TModel of \App\Models\BaseModel
     *
     * @param  class-string<TModel>  $modelClass
     * @param  list<string>  $names
     * @return Collection<string, TModel>
     */
    private function createNamed(string $modelClass, array $names): Collection
    {
        /** @var Collection<string, TModel> $records */
        $records = collect();

        foreach ($names as $name) {
            /** @var TModel $record */
            $record = $modelClass::create(['name' => $name]);

            $records->put($name, $record);
        }

        return $records;
    }

    /**
     * @param  Collection<string, ProductCategory>  $categories
     */
    private function createProducts(Collection $categories): void
    {
        $standardRate = TaxRate::standard();
        $reducedRate = TaxRate::factory()->reduced()->create();

        foreach (self::PRODUCTS as [$category, $name, $unitPrice, $unit, $isReduced]) {
            Product::create([
                'name' => $name,
                'product_category_id' => $categories->get($category)?->id,
                'unit_price' => $unitPrice,
                'unit' => $unit,
                'tax_rate_id' => $isReduced ? $reducedRate->id : $standardRate?->id,
            ]);
        }
    }

    /**
     * 社員。先頭 8 名は営業部に置き、商談の担当営業として使う。
     *
     * @param  Collection<string, Department>  $departments
     * @param  Collection<string, Position>  $positions
     */
    private function createEmployees(Collection $departments, Collection $positions): void
    {
        Employee::factory()
            ->count(24)
            ->create()
            ->each(function (Employee $employee, int $index) use ($departments, $positions): void {
                $employee->forceFill([
                    'department_id' => $index < 8
                        ? $departments->get('営業部')?->id
                        : fake()->randomElement($departments->values()->all())->id,
                    'position_id' => fake()->randomElement($positions->values()->all())->id,
                ])->saveQuietly();
            });

        // 退職者も混ぜて、絞り込みを確認できるようにする
        Employee::factory()->retired()->count(4)->create();

        $this->linkDemoUsers();
    }

    /**
     * デモ用ユーザーを社員に紐付ける(活動履歴の実施者が既定で選ばれるようにする)。
     */
    private function linkDemoUsers(): void
    {
        $salesReps = Employee::query()->orderBy('id')->limit(2)->get();

        foreach (['admin@example.com', 'staff@example.com'] as $index => $email) {
            $user = User::firstWhere('email', $email);
            $employee = $salesReps->get($index);

            if ($user !== null && $employee !== null) {
                $employee->forceFill(['user_id' => $user->id])->saveQuietly();
            }
        }
    }

    /**
     * 取引先。得意先を多めにし、一部は無効にしておく。
     */
    private function createPartners(): void
    {
        foreach (self::PARTNER_PLAN as [$type, $count]) {
            Partner::factory()->count($count)->create(['partner_type' => $type]);
        }

        // 取引が終わった会社の想定(一覧の絞り込み確認用)
        Partner::query()->orderByDesc('id')->limit(3)->get()
            ->each(fn (Partner $partner) => $partner->forceFill(['is_active' => false])->saveQuietly());
    }
}
