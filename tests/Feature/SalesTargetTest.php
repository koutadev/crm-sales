<?php

namespace Tests\Feature;

use App\Enums\OrganizationType;
use App\Enums\RoleName;
use App\Enums\TargetScope;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 売上目標マスタの検証。
 *
 * 粒度（全社 / 地域 / エリア / 店舗 / 担当者）ごとに目標を持て、
 * 前の期間からまとめて複製できることを見る。
 */
class SalesTargetTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    private function store(): Organization
    {
        $region = Organization::create(['name' => '東日本地域', 'type' => OrganizationType::Region, 'is_active' => true]);
        $area = Organization::create(['name' => '首都圏エリア', 'type' => OrganizationType::Area, 'parent_id' => $region->id, 'is_active' => true]);

        return Organization::create([
            'name' => '東京本店',
            'type' => OrganizationType::Store,
            'parent_id' => $area->id,
            'prefecture' => '東京都',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function targets_can_be_set_for_each_scope(): void
    {
        $this->actingAsRole(RoleName::Admin);
        $store = $this->store();

        // 全社（対象なし）
        $this->post(route('masters.sales-targets.store'), [
            'scope' => TargetScope::Company->value,
            'year' => 2026,
            'month' => 8,
            'amount' => 22100000,
            'is_active' => '1',
        ])->assertRedirect();

        // 店舗
        $this->post(route('masters.sales-targets.store'), [
            'scope' => TargetScope::Store->value,
            'target_id' => $store->id,
            'year' => 2026,
            'month' => 8,
            'amount' => 1600000,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame(2, SalesTarget::query()->count());

        $company = SalesTarget::query()->where('scope', TargetScope::Company->value)->firstOrFail();
        $this->assertSame('TGT-0001', $company->code);
        $this->assertNull($company->target_id);
        $this->assertSame('2026年8月', $company->periodLabel());
        $this->assertSame(2026, $company->fiscalYear());
    }

    #[Test]
    public function the_target_must_match_the_scope(): void
    {
        $this->actingAsRole(RoleName::Admin);
        $store = $this->store();
        $region = Organization::query()->ofType(OrganizationType::Region)->firstOrFail();

        // 全社に対象は指定できない
        $this->post(route('masters.sales-targets.store'), [
            'scope' => TargetScope::Company->value,
            'target_id' => $store->id,
            'year' => 2026, 'month' => 8, 'amount' => 100,
        ])->assertSessionHasErrors('target_id');

        // 店舗の目標に地域は選べない
        $this->post(route('masters.sales-targets.store'), [
            'scope' => TargetScope::Store->value,
            'target_id' => $region->id,
            'year' => 2026, 'month' => 8, 'amount' => 100,
        ])->assertSessionHasErrors('target_id');

        // 対象は必須
        $this->post(route('masters.sales-targets.store'), [
            'scope' => TargetScope::Store->value,
            'year' => 2026, 'month' => 8, 'amount' => 100,
        ])->assertSessionHasErrors('target_id');
    }

    #[Test]
    public function the_same_target_cannot_be_registered_twice_for_one_month(): void
    {
        $this->actingAsRole(RoleName::Admin);

        SalesTarget::create([
            'scope' => TargetScope::Company, 'target_id' => null,
            'year' => 2026, 'month' => 8, 'amount' => 1000, 'is_active' => true,
        ]);

        $this->post(route('masters.sales-targets.store'), [
            'scope' => TargetScope::Company->value,
            'year' => 2026, 'month' => 8, 'amount' => 2000,
        ])->assertSessionHasErrors('year');
    }

    #[Test]
    public function targets_can_be_copied_from_another_month(): void
    {
        $this->actingAsRole(RoleName::Admin);
        $store = $this->store();

        SalesTarget::create([
            'scope' => TargetScope::Company, 'target_id' => null,
            'year' => 2026, 'month' => 7, 'amount' => 20600000, 'is_active' => true,
        ]);
        SalesTarget::create([
            'scope' => TargetScope::Store, 'target_id' => $store->id,
            'year' => 2026, 'month' => 7, 'amount' => 1500000, 'is_active' => true,
        ]);

        // 複製さきに 1 本だけ先に入れておく（上書きされないこと）
        SalesTarget::create([
            'scope' => TargetScope::Company, 'target_id' => null,
            'year' => 2026, 'month' => 8, 'amount' => 22100000, 'is_active' => true,
        ]);

        $this->post(route('masters.sales-targets.duplicate'), [
            'from_year' => 2026, 'from_month' => 7,
            'to_year' => 2026, 'to_month' => 8,
        ])->assertRedirect();

        $august = SalesTarget::query()->forMonth(2026, 8)->get();
        $this->assertCount(2, $august);

        // すでにあった全社目標はそのまま
        $this->assertSame(
            22100000,
            $august->firstWhere('scope', TargetScope::Company)?->amount,
        );

        // 店舗の目標は複製された
        $this->assertSame(
            1500000,
            $august->firstWhere('scope', TargetScope::Store)?->amount,
        );
    }

    #[Test]
    public function copying_from_an_empty_month_says_so(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $this->post(route('masters.sales-targets.duplicate'), [
            'from_year' => 2026, 'from_month' => 1,
            'to_year' => 2026, 'to_month' => 2,
        ])->assertRedirect();

        $this->assertSame(0, SalesTarget::query()->count());
    }

    #[Test]
    public function the_list_shows_the_target_of_each_scope(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $store = $this->store();
        $employee = Employee::factory()->create(['name' => '山田 太郎']);

        SalesTarget::create([
            'scope' => TargetScope::Store, 'target_id' => $store->id,
            'year' => 2026, 'month' => 8, 'amount' => 1600000, 'is_active' => true,
        ]);
        SalesTarget::create([
            'scope' => TargetScope::Employee, 'target_id' => $employee->id,
            'year' => 2026, 'month' => 8, 'amount' => 800000, 'is_active' => true,
        ]);

        $this->get(route('masters.sales-targets.index', ['reset' => 1]))
            ->assertOk()
            ->assertSee('2026年8月')
            ->assertSee('東京本店')
            ->assertSee('山田 太郎')
            ->assertSee('1,600,000')
            ->assertSee('前の期間から複製');
    }

    #[Test]
    public function only_managers_can_change_targets(): void
    {
        $this->actingAsRole(RoleName::Viewer);

        $this->get(route('masters.sales-targets.index', ['reset' => 1]))->assertOk();
        $this->get(route('masters.sales-targets.create'))->assertForbidden();
        $this->post(route('masters.sales-targets.duplicate'), [
            'from_year' => 2026, 'from_month' => 7,
            'to_year' => 2026, 'to_month' => 8,
        ])->assertForbidden();
    }
}
