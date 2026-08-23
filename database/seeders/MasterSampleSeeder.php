<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * 動作確認用のマスタサンプルデータ(本番環境では実行しない)。
 *
 * 一覧のページング(1ページ20件)や検索を確認できるよう、
 * 各マスタを 20 件以上作成する。
 */
class MasterSampleSeeder extends Seeder
{
    public function run(): void
    {
        // 既にサンプルが入っている場合は二重登録しない
        if (Employee::query()->exists()) {
            return;
        }

        $departments = Department::factory()->count(5)->create();
        $positions = Position::factory()->count(5)->create();
        $categories = ProductCategory::factory()->count(5)->create();

        // 既定の標準税率は TaxRateSeeder が投入済み。軽減税率も足して絞り込みを確認できるようにする
        $reducedTaxRate = TaxRate::factory()->reduced()->create();

        Employee::factory()
            ->count(28)
            ->create()
            ->each(function (Employee $employee) use ($departments, $positions): void {
                $employee->forceFill([
                    'department_id' => $departments->random()->id,
                    'position_id' => $positions->random()->id,
                ])->saveQuietly();
            });

        // 無効・退職のデータも混ぜて、絞り込みを確認できるようにする
        Employee::factory()->retired()->count(4)->create();

        Partner::factory()->count(26)->create();

        // 税率は未指定。Product の保存時フックで既定の標準税率が入る
        Product::factory()
            ->count(24)
            ->create()
            ->each(function (Product $product, int $index) use ($categories, $reducedTaxRate): void {
                $product->forceFill([
                    'product_category_id' => $categories->random()->id,
                    // 一部の商品は軽減税率にしておく
                    'tax_rate_id' => $index % 6 === 0 ? $reducedTaxRate->id : $product->tax_rate_id,
                ])->saveQuietly();
            });
    }
}
