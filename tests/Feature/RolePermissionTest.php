<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ロール・権限による画面 / メニューの出し分けの検証。
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    #[Test]
    public function seeded_roles_have_the_permissions_defined_in_the_enum(): void
    {
        foreach (RoleName::cases() as $role) {
            $user = $this->userWithRole($role);

            foreach (PermissionName::cases() as $permission) {
                $expected = in_array($permission, $role->permissions(), strict: true);

                $this->assertSame(
                    $expected,
                    $user->can($permission->value),
                    "{$role->value} の {$permission->value} 権限が定義と一致しません。",
                );
            }
        }
    }

    #[Test]
    public function admin_can_view_the_activity_log(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('activity-logs.index'))
            ->assertOk();
    }

    #[Test]
    public function staff_and_viewer_cannot_view_the_activity_log(): void
    {
        foreach ([RoleName::Staff, RoleName::Viewer] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('activity-logs.index'))
                ->assertForbidden();
        }
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('activity-logs.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function a_user_without_any_role_cannot_open_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function the_activity_log_menu_is_only_visible_to_authorized_users(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('activity-logs.index'));

        $this->actingAs($this->userWithRole(RoleName::Staff))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('activity-logs.index'));
    }

    #[Test]
    public function new_registrations_receive_the_default_role(): void
    {
        $this->post(route('register'), [
            'name' => '新規 太郎',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'new-user@example.com')->sole();

        $this->assertTrue($user->hasRole(RoleName::default()->value));
    }
}
