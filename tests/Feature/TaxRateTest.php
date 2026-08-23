<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 税率マスタ(世代管理)と、商品マスタへの税率紐付けの検証。
 */
class TaxRateTest extends TestCase
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
    public function a_tax_rate_can_be_registered(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.tax-rates.store'), [
            'name' => '標準',
            'rate_percent' => '10',
            'effective_from' => '2019-10-01',
            'is_active' => '1',
        ])->assertRedirect(route('masters.tax-rates.index'));

        $taxRate = TaxRate::query()->sole();

        $this->assertSame('標準', $taxRate->name);
        $this->assertSame(10, $taxRate->rate_percent);
        $this->assertSame('2019-10-01', $taxRate->effective_from->toDateString());
        $this->assertTrue($taxRate->is_active);
    }

    #[Test]
    public function a_rate_change_is_added_as_a_new_generation(): void
    {
        $this->seed(TaxRateSeeder::class);
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.tax-rates.store'), [
            'name' => '標準',
            'rate_percent' => '12',
            'effective_from' => '2026-10-01',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        // 既存の 10% は書き換えられず、世代として並ぶ
        $this->assertSame(2, TaxRate::query()->where('name', '標準')->count());

        // 既定の税率は基準日で切り替わる
        $this->assertSame(10, TaxRate::standard(Carbon::parse('2026-09-30'))?->rate_percent);
        $this->assertSame(12, TaxRate::standard(Carbon::parse('2026-10-01'))?->rate_percent);
    }

    #[Test]
    public function an_inactive_generation_is_not_used_as_the_default(): void
    {
        $this->seed(TaxRateSeeder::class);

        TaxRate::factory()->create([
            'rate_percent' => 12,
            'effective_from' => '2020-04-01',
            'is_active' => false,
        ]);

        $this->assertSame(10, TaxRate::standard()?->rate_percent);
    }

    #[Test]
    public function a_product_without_a_tax_rate_falls_back_to_the_standard_rate(): void
    {
        $this->seed(TaxRateSeeder::class);
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.products.store'), [
            'name' => 'ノートPC',
            'unit_price' => '198000',
            'unit' => '台',
            'tax_rate_id' => '',
            'is_active' => '1',
        ])->assertRedirect(route('masters.products.index'));

        $product = Product::query()->sole();

        $this->assertNotNull($product->tax_rate_id, '税率未選択でも NULL にはならない。');
        $this->assertSame(10, $product->taxRatePercent());
    }

    #[Test]
    public function a_product_keeps_the_selected_tax_rate(): void
    {
        $this->seed(TaxRateSeeder::class);
        $reduced = TaxRate::factory()->reduced()->create();

        $this->actingAsRole(RoleName::Staff);

        $this->post(route('masters.products.store'), [
            'name' => '飲食料品',
            'unit_price' => '1000',
            'tax_rate_id' => (string) $reduced->id,
            'is_active' => '1',
        ])->assertRedirect(route('masters.products.index'));

        $product = Product::query()->sole();

        $this->assertSame($reduced->id, $product->tax_rate_id);
        $this->assertSame(8, $product->taxRatePercent());
    }

    #[Test]
    public function the_tax_rate_is_shown_on_the_product_screens(): void
    {
        $this->seed(TaxRateSeeder::class);
        $this->actingAsRole(RoleName::Staff);

        $product = Product::factory()->create(['name' => '保守サービス']);

        $this->get(route('masters.products.index'))
            ->assertOk()
            ->assertSee('保守サービス')
            ->assertSee('10%');

        $this->get(route('masters.products.edit', $product->id))
            ->assertOk()
            ->assertSee('標準 10%');
    }

    #[Test]
    public function the_tax_rate_screens_can_be_opened(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $taxRate = TaxRate::factory()->create();

        $this->get(route('masters.tax-rates.index'))
            ->assertOk()
            ->assertSee('標準')
            ->assertSee('10%');

        $this->get(route('masters.tax-rates.create'))->assertOk();

        // 税率マスタはコードを持たないため、共通フォームのコード欄は出ない
        $this->get(route('masters.tax-rates.edit', $taxRate->id))
            ->assertOk()
            ->assertSee('適用開始日')
            ->assertDontSee('自動採番のため変更できません');
    }

    #[Test]
    public function validation_rejects_invalid_tax_rate_input(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->from(route('masters.tax-rates.create'))
            ->post(route('masters.tax-rates.store'), [
                'name' => '',
                'rate_percent' => '120',
                'effective_from' => 'not-a-date',
            ])
            ->assertSessionHasErrors(['name', 'rate_percent', 'effective_from']);

        $this->assertSame(0, TaxRate::query()->count());
    }

    #[Test]
    public function a_viewer_can_browse_but_not_manage_tax_rates(): void
    {
        $this->actingAsRole(RoleName::Viewer);

        $taxRate = TaxRate::factory()->create();

        $this->get(route('masters.tax-rates.index'))->assertOk();
        $this->get(route('masters.tax-rates.export'))->assertOk();

        $this->get(route('masters.tax-rates.create'))->assertForbidden();
        $this->post(route('masters.tax-rates.store'), ['name' => '標準'])->assertForbidden();
        $this->get(route('masters.tax-rates.edit', $taxRate->id))->assertForbidden();
        $this->delete(route('masters.tax-rates.destroy', $taxRate->id))->assertForbidden();
    }

    #[Test]
    public function a_tax_rate_can_be_soft_deleted(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $taxRate = TaxRate::factory()->create();

        $this->delete(route('masters.tax-rates.destroy', $taxRate->id))
            ->assertRedirect(route('masters.tax-rates.index'));

        $this->assertSoftDeleted($taxRate);
    }
}
