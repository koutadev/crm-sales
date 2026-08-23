<?php

namespace Tests\Unit;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ロール・権限の定義そのものの検証(DB を使わない)。
 */
class RoleDefinitionTest extends TestCase
{
    #[Test]
    public function admin_has_every_permission(): void
    {
        $this->assertSame(
            PermissionName::cases(),
            RoleName::Admin->permissions(),
            '管理者はすべての権限を持つ必要があります。',
        );
    }

    #[Test]
    public function viewer_cannot_modify_anything(): void
    {
        $writePermissions = [
            PermissionName::MasterManage,
            PermissionName::UserManage,
        ];

        foreach ($writePermissions as $permission) {
            $this->assertNotContains(
                $permission,
                RoleName::Viewer->permissions(),
                '閲覧者に更新系の権限を与えてはいけません。',
            );
        }
    }

    #[Test]
    public function the_default_role_for_new_users_is_the_least_privileged(): void
    {
        $this->assertSame(RoleName::Viewer, RoleName::default());
    }

    #[Test]
    public function every_role_and_permission_has_a_japanese_label(): void
    {
        foreach (RoleName::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }

        foreach (PermissionName::cases() as $permission) {
            $this->assertNotSame('', $permission->label());
        }
    }
}
