<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ロールと権限・既定の税率はアプリの動作に必要なため、環境を問わず投入する
        $this->call([
            RolePermissionSeeder::class,
            TaxRateSeeder::class,
        ]);

        // 動作確認用のデータは本番以外のみ
        if (! app()->isProduction()) {
            // デモの数字とスクリーンショットを再現できるよう、乱数を固定する
            fake()->seed(20260824);
            mt_srand(20260824);

            $this->call([
                DemoUserSeeder::class,
                MasterSampleSeeder::class,
                CrmSampleSeeder::class,
            ]);
        }
    }
}
