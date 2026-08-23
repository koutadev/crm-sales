<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase を使うテストでシーダーを実行する。
     *
     * ロール・権限はアプリの前提条件(新規登録時に既定ロールを付与する等)なので、
     * 全テストで DB に投入しておく。
     */
    protected bool $seed = true;

    /** @var class-string<Seeder> */
    protected string $seeder = RolePermissionSeeder::class;
}
