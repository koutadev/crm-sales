<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * 既定の標準税率(10%)。
 *
 * 商品マスタの税率未選択時のフォールバックに使うため、環境を問わず投入する。
 * 税率が変わったときはこのレコードを書き換えず、
 * 税率マスタ画面から適用開始日の新しいレコードを追加すること。
 */
class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        TaxRate::query()->firstOrCreate(
            [
                'name' => config('tax.default_rate_name'),
                // 十分に過去の日付。日本の標準税率 10% の適用開始日に合わせている
                'effective_from' => '2019-10-01',
            ],
            ['rate_percent' => 10],
        );
    }
}
