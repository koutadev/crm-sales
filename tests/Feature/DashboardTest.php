<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ダッシュボードの枠（KPI カード・グラフ・最近の操作ログ）の検証。
 */
class DashboardTest extends TestCase
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
    public function it_shows_kpi_cards_built_from_master_data(): void
    {
        $this->actingAsRole(RoleName::Admin);

        Employee::factory()->count(3)->create();
        Partner::factory()->count(2)->create();
        Product::factory()->create();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('社員');
        $response->assertSee('取引先');
        $response->assertSee('商品');

        $kpis = $response->viewData('kpis');

        $this->assertCount(4, $kpis);
        $this->assertSame(3, $kpis[0]->value);
        $this->assertSame(2, $kpis[1]->value);
        $this->assertSame(1, $kpis[2]->value);
    }

    #[Test]
    public function it_renders_two_charts_as_chart_js_configuration(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $category = ProductCategory::factory()->create(['name' => 'IT機器']);
        Product::factory()->count(2)->create(['product_category_id' => $category->id]);
        Partner::factory()->create();

        $response = $this->get(route('dashboard'));

        $charts = $response->viewData('charts');
        $this->assertCount(2, $charts);

        $doughnut = $charts[0]->toChartJs();
        $this->assertSame('doughnut', $doughnut['type']);
        $this->assertSame(['得意先', '仕入先', '両方'], $doughnut['data']['labels']);

        $bar = $charts[1]->toChartJs();
        $this->assertSame('bar', $bar['type']);
        $this->assertContains('IT機器', $bar['data']['labels']);

        // Blade 側は canvas の data 属性に JSON を載せるだけ
        $response->assertSee('id="chart-partner-type"', false);
        $response->assertSee('id="chart-product-category"', false);
    }

    #[Test]
    public function the_recent_activity_panel_is_limited_to_users_who_may_read_logs(): void
    {
        $this->actingAsRole(RoleName::Admin);
        Employee::factory()->create();

        $admin = $this->get(route('dashboard'));
        $admin->assertSee('最近の操作');
        $this->assertNotNull($admin->viewData('recentActivities'));

        // 担当者は操作ログの権限を持たないため、パネルごと出さない
        $this->actingAsRole(RoleName::Staff);
        $staff = $this->get(route('dashboard'));
        $staff->assertDontSee('最近の操作');
        $this->assertNull($staff->viewData('recentActivities'));
    }

    #[Test]
    public function a_user_without_master_permission_sees_no_kpi_or_chart(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionName::DashboardView->value);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame([], $response->viewData('kpis'));
        $this->assertSame([], $response->viewData('charts'));
        $response->assertSee('表示できる情報がありません');
    }

    #[Test]
    public function a_chart_without_data_is_reported_as_empty(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $charts = $this->get(route('dashboard'))->viewData('charts');

        $this->assertTrue($charts[0]->isEmpty(), 'データが 0 件ならグラフは空として扱う');
    }
}
