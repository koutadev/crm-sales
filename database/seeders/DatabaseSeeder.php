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
            $this->call([
                DemoUserSeeder::class,
                MasterSampleSeeder::class,
                CrmSampleSeeder::class,
            ]);
        }
    }
}
