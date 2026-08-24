<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\EntityType;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 共通マスタの CRUD と、権限による制御の検証。
 */
class MasterCrudTest extends TestCase
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
    public function an_employee_can_be_registered_with_an_auto_generated_code(): void
    {
        $user = $this->actingAsRole(RoleName::Staff);
        $department = Department::factory()->create();

        $response = $this->post(route('masters.employees.store'), [
            'name' => '山田 太郎',
            'department_id' => $department->id,
            'email' => 'yamada@example.com',
            'employment_status' => EmploymentStatus::Active->value,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('masters.employees.index'));

        $employee = Employee::query()->sole();

        $this->assertSame('EMP-0001', $employee->code);
        $this->assertSame('山田 太郎', $employee->name);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertTrue($employee->is_active);

        // 共通仕様(作成者の自動記録)も効いている
        $this->assertSame($user->id, $employee->created_by);
    }

    #[Test]
    public function the_code_is_not_accepted_from_the_form(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.employees.store'), [
            'code' => 'EMP-9999',
            'name' => '山田 太郎',
            'employment_status' => EmploymentStatus::Active->value,
        ]);

        $this->assertSame('EMP-0001', Employee::query()->sole()->code, 'コードは常に自動採番される。');
    }

    #[Test]
    public function an_employee_can_be_updated(): void
    {
        $editor = $this->actingAsRole(RoleName::Staff);
        $employee = Employee::factory()->create(['name' => '変更前']);

        $this->put(route('masters.employees.update', $employee->id), [
            'name' => '変更後',
            'employment_status' => EmploymentStatus::Leave->value,
            'is_active' => '1',
        ])->assertRedirect(route('masters.employees.index'));

        $employee->refresh();

        $this->assertSame('変更後', $employee->name);
        $this->assertSame(EmploymentStatus::Leave, $employee->employment_status);
        $this->assertSame($editor->id, $employee->updated_by);
    }

    #[Test]
    public function the_active_flag_can_be_switched_off_and_on(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $employee = Employee::factory()->create();

        // チェックボックス未送信 = 無効
        $this->put(route('masters.employees.update', $employee->id), [
            'name' => $employee->name,
            'employment_status' => EmploymentStatus::Active->value,
        ]);
        $this->assertFalse($employee->refresh()->is_active);

        $this->put(route('masters.employees.update', $employee->id), [
            'name' => $employee->name,
            'employment_status' => EmploymentStatus::Active->value,
            'is_active' => '1',
        ]);
        $this->assertTrue($employee->refresh()->is_active);
    }

    #[Test]
    public function deleting_a_master_only_marks_it_as_deleted(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $employee = Employee::factory()->create();

        $this->delete(route('masters.employees.destroy', $employee->id))
            ->assertRedirect(route('masters.employees.index'));

        $this->assertSoftDeleted($employee);
        $this->assertNotNull(Employee::withTrashed()->find($employee->id));
    }

    #[Test]
    public function only_an_administrator_can_restore_a_deleted_master(): void
    {
        $employee = Employee::factory()->create();
        $employee->delete();

        $this->actingAsRole(RoleName::Staff);
        $this->post(route('masters.employees.restore', $employee->id))->assertForbidden();
        $this->assertSoftDeleted($employee);

        $this->actingAsRole(RoleName::Admin);
        $this->post(route('masters.employees.restore', $employee->id))
            ->assertRedirect(route('masters.employees.index'));

        $this->assertNotSoftDeleted($employee->fresh());
    }

    #[Test]
    public function a_viewer_can_browse_but_not_modify(): void
    {
        $this->actingAsRole(RoleName::Viewer);
        $employee = Employee::factory()->create();

        $this->get(route('masters.employees.index'))->assertOk();
        $this->get(route('masters.employees.export'))->assertOk();

        $this->get(route('masters.employees.create'))->assertForbidden();
        $this->post(route('masters.employees.store'), ['name' => 'x'])->assertForbidden();
        $this->get(route('masters.employees.edit', $employee->id))->assertForbidden();
        $this->delete(route('masters.employees.destroy', $employee->id))->assertForbidden();
    }

    #[Test]
    public function validation_rejects_invalid_input(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->from(route('masters.employees.create'))
            ->post(route('masters.employees.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'employment_status' => 'unknown',
            ])
            ->assertSessionHasErrors(['name', 'email', 'employment_status']);

        $this->assertSame(0, Employee::query()->count());
    }

    #[Test]
    public function an_employee_email_must_be_unique_among_living_records(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $existing = Employee::factory()->create(['email' => 'duplicate@example.com']);

        $this->post(route('masters.employees.store'), [
            'name' => '重複 太郎',
            'email' => 'duplicate@example.com',
            'employment_status' => EmploymentStatus::Active->value,
        ])->assertSessionHasErrors('email');

        // 削除済みのメールアドレスは再利用できる
        $existing->delete();

        $this->post(route('masters.employees.store'), [
            'name' => '再利用 太郎',
            'email' => 'duplicate@example.com',
            'employment_status' => EmploymentStatus::Active->value,
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_partner_can_be_registered(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.partners.store'), [
            'name' => 'テスト商事株式会社',
            'partner_type' => PartnerType::Both->value,
            'entity_type' => EntityType::Corporate->value,
            'phone' => '03-1234-5678',
            'is_active' => '1',
        ])->assertRedirect(route('masters.partners.index'));

        $partner = Partner::query()->sole();

        $this->assertSame('PTR-0001', $partner->code);
        $this->assertSame(PartnerType::Both, $partner->partner_type);
        $this->assertTrue($partner->partner_type->isCustomer());
        $this->assertTrue($partner->partner_type->isSupplier());
    }

    #[Test]
    public function a_product_can_be_registered(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.products.store'), [
            'name' => 'ノートPC',
            'unit_price' => '198000',
            'unit' => '台',
            'is_active' => '1',
        ])->assertRedirect(route('masters.products.index'));

        $product = Product::query()->sole();

        $this->assertSame('PRD-0001', $product->code);
        $this->assertSame(198000, $product->unit_price);
    }

    #[Test]
    public function a_sub_master_can_be_registered(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.departments.store'), [
            'name' => '開発部',
            'is_active' => '1',
        ])->assertRedirect(route('masters.departments.index'));

        $this->assertSame('DEP-0001', Department::query()->sole()->code);
    }

    #[Test]
    public function master_changes_are_recorded_in_the_activity_log(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.departments.store'), ['name' => '開発部', 'is_active' => '1']);

        $department = Department::query()->sole();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'subject_type' => Department::class,
            'subject_id' => $department->id,
        ]);
    }
}
