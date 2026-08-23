<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ロールと権限を App\Enums の定義どおりに DB へ反映する。
 *
 * 何度実行しても同じ結果になる(冪等)ので、デプロイ時に毎回流してよい。
 *
 *   docker compose exec app php artisan db:seed --class=RolePermissionSeeder
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, $guard);
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, $guard);

            // enum 側を唯一の定義元とし、DB 側の差分は毎回上書きする
            $role->syncPermissions($roleName->permissionValues());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
