<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductCategory;
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

        Product::factory()
            ->count(24)
            ->create()
            ->each(function (Product $product) use ($categories): void {
                $product->forceFill(['product_category_id' => $categories->random()->id])->saveQuietly();
            });
    }
}
