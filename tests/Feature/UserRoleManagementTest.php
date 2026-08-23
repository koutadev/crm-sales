<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ユーザー管理(ロールの付け替え)の検証。
 */
class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function an_administrator_can_change_a_users_roles(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $target = User::factory()->create();
        $target->assignRole(RoleName::Viewer->value);

        $this->put(route('users.update', $target->id), ['roles' => [RoleName::Staff->value]])
            ->assertRedirect(route('users.index'));

        $target->refresh();

        $this->assertTrue($target->hasRole(RoleName::Staff->value));
        $this->assertFalse($target->hasRole(RoleName::Viewer->value));
    }

    #[Test]
    public function all_roles_can_be_removed_from_another_user(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $target = User::factory()->create();
        $target->assignRole(RoleName::Staff->value);

        $this->put(route('users.update', $target->id), []);

        $this->assertCount(0, $target->fresh()?->getRoleNames() ?? collect());
    }

    #[Test]
    public function an_administrator_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->actingAsRole(RoleName::Admin);

        $this->from(route('users.edit', $admin->id))
            ->put(route('users.update', $admin->id), ['roles' => [RoleName::Viewer->value]])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()?->hasRole(RoleName::Admin->value));
    }

    #[Test]
    public function an_unknown_role_is_rejected(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $target = User::factory()->create();

        $this->put(route('users.update', $target->id), ['roles' => ['superuser']])
            ->assertSessionHasErrors('roles.0');
    }

    #[Test]
    public function users_without_the_permission_cannot_open_the_screen(): void
    {
        foreach ([RoleName::Staff, RoleName::Viewer] as $role) {
            $this->actingAsRole($role);

            $this->get(route('users.index'))->assertForbidden();
            $this->get(route('users.edit', 1))->assertForbidden();
        }
    }

    #[Test]
    public function the_user_list_can_be_searched(): void
    {
        $this->actingAsRole(RoleName::Admin);

        User::factory()->create(['name' => '検索対象 花子', 'email' => 'hanako@example.com']);
        User::factory()->count(3)->create();

        $items = $this->get(route('users.index', ['q' => 'hanako@example.com']))
            ->viewData('table')->items();

        $this->assertCount(1, $items);
    }
}
