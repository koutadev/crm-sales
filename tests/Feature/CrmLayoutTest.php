<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Crm\CrmMasterCatalog;
use App\Support\Crm\CrmNavigationMenu;
use App\Support\Masters\MasterCatalog;
use App\Support\Navigation\NavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 共通デザインシステムの取り込み(3-A)の検証。
 *
 * 左サイドナビ・パンくず・共通部品が CRM 側でも使えることを見る。
 * 金額・集計のロジックには手を入れていないので、そちらは既存のテストが担保する。
 */
class CrmLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    #[Test]
    public function the_crm_menu_and_catalog_are_bound(): void
    {
        $this->assertInstanceOf(CrmNavigationMenu::class, app(NavigationMenu::class));
        $this->assertInstanceOf(CrmMasterCatalog::class, app(MasterCatalog::class));
    }

    #[Test]
    public function the_sidebar_is_grouped_into_sales_masters_and_admin(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                '営業', 'ダッシュボード', '商談', '顧客',
                'マスタ', 'マスタ管理',
                '管理', 'ユーザー管理', '操作ログ',
            ])
            ->assertSee(route('deals.index'))
            ->assertSee(route('customers.index'))
            ->assertSee(route('masters.index'))
            ->assertSee('メインメニュー');
    }

    #[Test]
    public function the_menu_is_filtered_by_permission(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Staff))
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee(route('deals.index'))
            ->assertDontSee(route('users.index'))
            ->assertDontSee(route('activity-logs.index'));
    }

    #[Test]
    public function each_screen_shows_where_it_is(): void
    {
        $user = $this->userWithRole(RoleName::Admin);

        $this->actingAs($user)->get(route('deals.index'))->assertOk()
            ->assertSeeInOrder(['ダッシュボード', '営業', '商談'])
            ->assertSee('aria-current="page"', false);

        $this->actingAs($user)->get(route('customers.index'))->assertOk()
            ->assertSeeInOrder(['ダッシュボード', '営業', '顧客']);

        $this->actingAs($user)->get(route('masters.tax-rates.index'))->assertOk()
            ->assertSeeInOrder(['ダッシュボード', 'マスタ', '税率']);
    }

    #[Test]
    public function the_master_hub_includes_the_tax_rates(): void
    {
        TaxRate::factory()->count(2)->create();

        $response = $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('masters.index'))
            ->assertOk();

        $response->assertSee('税率')
            ->assertSee(route('masters.tax-rates.index'))
            // 共通基盤のマスタも並ぶ
            ->assertSee('社員')
            ->assertSee('取引先')
            ->assertSee('商品');

        $counts = $response->viewData('counts');

        $this->assertSame(2, $counts['tax_rates']);
    }

    #[Test]
    public function a_master_row_opens_its_detail(): void
    {
        $taxRate = TaxRate::factory()->create(['name' => '標準', 'rate_percent' => 10]);

        $this->actingAs($this->userWithRole(RoleName::Staff))
            ->get(route('masters.tax-rates.index'))
            ->assertOk()
            ->assertSee(route('masters.tax-rates.detail', $taxRate->id), false);

        $this->actingAs($this->userWithRole(RoleName::Staff))
            ->get(route('masters.tax-rates.detail', $taxRate->id))
            ->assertOk()
            ->assertSee('税率 — 標準')
            ->assertSee('適用開始日')
            ->assertSee('10%');
    }

    #[Test]
    public function the_shared_components_are_available(): void
    {
        $this->withViewErrors([]);

        $this->assertStringContainsString('bg-primary', Blade::render('<x-button>保存</x-button>'));
        $this->assertStringContainsString('必須', Blade::render('<x-form.text name="title" label="件名" required />'));
        $this->assertStringContainsString('role="combobox"', Blade::render('<x-form.combobox name="partner_id" :options="[]" />'));
        $this->assertStringContainsString('role="dialog"', Blade::render('<x-modal name="a" title="t">本文</x-modal>'));
        $this->assertStringContainsString('x-data="datepicker(', Blade::render('<x-datepicker name="d" />'));
        $this->assertStringContainsString('role="grid"', Blade::render('<x-date-range name="closed" />'));
        $this->assertStringContainsString('bg-emerald-100', Blade::render('<x-badge tone="success">受注</x-badge>'));
        $this->assertStringContainsString('aria-sort', Blade::render(
            '<x-table :columns="$c" sort="code" :sort-url="$u">行</x-table>',
            ['c' => [['key' => 'code', 'label' => 'コード', 'sortable' => true]], 'u' => fn ($column): string => '#'],
        ));
    }

    #[Test]
    public function the_deal_list_still_shows_its_summary_on_the_new_layout(): void
    {
        // 金額まわりは触っていないことの念のための確認(詳しくは DealScreenTest)
        $this->actingAs($this->userWithRole(RoleName::Staff))
            ->get(route('deals.index', ['reset' => 1]))
            ->assertOk()
            ->assertSee('合計(税込)')
            ->assertSee('加重見込み(税込)');
    }
}
