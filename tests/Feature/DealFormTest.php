<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商談と明細の登録・編集、および内税の金額計算の検証。
 *
 * 金額はすべて手計算と突き合わせている。
 */
class DealFormTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    private Employee $employee;

    private TaxRate $standardRate;

    private TaxRate $reducedRate;

    private Product $standardProduct;

    private Product $reducedProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Partner::factory()->create([
            'name' => 'テスト商事株式会社',
            'partner_type' => PartnerType::Customer,
        ]);

        $this->employee = Employee::factory()->create(['name' => '営業 太郎']);

        $this->standardRate = TaxRate::factory()->create(['name' => '標準', 'rate_percent' => 10]);
        $this->reducedRate = TaxRate::factory()->reduced()->create();

        $this->standardProduct = Product::factory()->create([
            'name' => '保守サービス',
            'unit_price' => 1000,
            'tax_rate_id' => $this->standardRate->id,
        ]);

        $this->reducedProduct = Product::factory()->create([
            'name' => '飲食料品',
            'unit_price' => 1080,
            'tax_rate_id' => $this->reducedRate->id,
        ]);
    }

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function payload(array $items = [], array $overrides = []): array
    {
        return array_merge([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'title' => '基幹システム刷新',
            'status' => DealStatus::Prospect->value,
            'probability' => 50,
            'expected_close_date' => '2026-12-31',
            'items' => $items,
        ], $overrides);
    }

    #[Test]
    public function a_deal_is_created_with_a_year_based_code_and_calculated_amounts(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('deals.store'), $this->payload([
            ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 1000],
            ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 1000],
        ]))->assertRedirect(route('customers.show', ['id' => $this->customer->id, 'tab' => 'deals']));

        $deal = Deal::query()->sole();

        $this->assertStringStartsWith('DEAL-'.now()->format('Y').'-', $deal->code);

        // 税込 2,000 / 1.1 = 1,818.18… → 消費税 181.81… → 切り捨て 181
        $this->assertSame(2000, $deal->amount_total);

        $items = $deal->items()->orderBy('id')->get();

        $this->assertCount(2, $items);
        $this->assertSame(2000, $items->sum('amount_incl_tax'));
        $this->assertSame(181, $items->sum('tax_amount'), '1 明細ずつ切り捨てず、税率ごとに 1 回だけ切り捨てる。');
        $this->assertSame(1819, $items->sum('amount_excl_tax'));

        // 明細には確定時点の税率がコピーされている
        $this->assertSame(10, $items[0]->tax_rate_percent);
        $this->assertSame($this->standardRate->id, $items[0]->tax_rate_id);
    }

    #[Test]
    public function amounts_with_mixed_tax_rates_match_the_manual_calculation(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('deals.store'), $this->payload([
            ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 10800],
            ['product_id' => $this->reducedProduct->id, 'quantity' => 1, 'unit_price' => 1080],
        ]))->assertSessionHasNoErrors();

        $deal = Deal::query()->sole();
        $summary = $deal->load('items')->amountSummary();

        // 10%: 10,800 / 1.1 = 9,818.18… → 消費税 981
        //  8%:  1,080 / 1.08 = 1,000     → 消費税 80
        $this->assertSame(11880, $deal->amount_total);
        $this->assertSame(11880, $summary->totalInclTax());
        $this->assertSame(1061, $summary->totalTax());
        $this->assertSame(10819, $summary->totalExclTax());

        $this->assertSame(981, $summary->rateAmounts[0]->taxAmount);
        $this->assertSame(80, $summary->rateAmounts[1]->taxAmount);

        // 保存されている明細の内訳も一致する
        $items = $deal->items()->orderBy('id')->get();
        $this->assertSame(1061, $items->sum('tax_amount'));
        $this->assertSame(10819, $items->sum('amount_excl_tax'));
    }

    #[Test]
    public function the_total_is_recalculated_when_items_are_added_updated_or_removed(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('deals.store'), $this->payload([
            ['product_id' => $this->standardProduct->id, 'quantity' => 2, 'unit_price' => 1000],
        ]))->assertSessionHasNoErrors();

        $deal = Deal::query()->sole();
        $this->assertSame(2000, $deal->amount_total);

        $itemId = $deal->items()->sole()->id;

        // 数量を変え、行を 1 つ足す
        $this->put(route('deals.update', $deal->id), $this->payload([
            ['id' => $itemId, 'product_id' => $this->standardProduct->id, 'quantity' => 3, 'unit_price' => 1000],
            ['product_id' => $this->reducedProduct->id, 'quantity' => 1, 'unit_price' => 1080],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(4080, $deal->refresh()->amount_total);
        $this->assertSame(2, $deal->items()->count());

        // 行を消す(画面から消えた明細は論理削除される)
        $this->put(route('deals.update', $deal->id), $this->payload([
            ['id' => $itemId, 'product_id' => $this->standardProduct->id, 'quantity' => 3, 'unit_price' => 1000],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(3000, $deal->refresh()->amount_total);
        $this->assertSame(1, $deal->items()->count());

        // 画面を通さず明細を消しても、モデル側で合計が追従する
        DealItem::query()->findOrFail($itemId)->delete();

        $this->assertSame(0, $deal->refresh()->amount_total);
    }

    #[Test]
    public function confirmed_amounts_do_not_change_when_the_masters_change(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('deals.store'), $this->payload([
            ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 11000],
        ]))->assertSessionHasNoErrors();

        $deal = Deal::query()->sole();
        $item = $deal->items()->sole();

        $this->assertSame(11000, $deal->amount_total);
        $this->assertSame(1000, $item->tax_amount);

        // 商品マスタの単価と、税率マスタの税率を後から変更する
        $this->standardProduct->update(['unit_price' => 99999]);
        $this->standardRate->update(['rate_percent' => 12]);

        $deal->refresh();
        $item->refresh();

        $this->assertSame(11000, $deal->amount_total, 'マスタを変えても確定済みの金額は動かない。');
        $this->assertSame(11000, $item->unit_price);
        $this->assertSame(10, $item->tax_rate_percent, '税率はスナップショットのまま。');
        $this->assertSame(1000, $item->tax_amount);

        // 明細をそのまま保存し直しても、スナップショットは引き継がれる
        $this->put(route('deals.update', $deal->id), $this->payload([
            ['id' => $item->id, 'product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 11000],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(10, $item->refresh()->tax_rate_percent);
        $this->assertSame(11000, $deal->refresh()->amount_total);
    }

    #[Test]
    public function a_new_line_picks_up_the_current_master_values(): void
    {
        $this->actingAsRole(RoleName::Staff);

        // 税率マスタが 12% の世代に変わったあとに追加した明細は 12% を写し取る
        $this->standardRate->update(['rate_percent' => 12]);

        $this->post(route('deals.store'), $this->payload([
            ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 11200],
        ]))->assertSessionHasNoErrors();

        $item = Deal::query()->sole()->items()->sole();

        $this->assertSame(12, $item->tax_rate_percent);
        // 11,200 / 1.12 = 10,000 → 消費税 1,200
        $this->assertSame(1200, $item->tax_amount);
        $this->assertSame(10000, $item->amount_excl_tax);
    }

    #[Test]
    public function a_deal_cannot_be_won_without_items(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->from(route('deals.create'))
            ->post(route('deals.store'), $this->payload([], [
                'status' => DealStatus::Won->value,
                'ordered_at' => '2026-08-01',
            ]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Deal::query()->count());
    }

    #[Test]
    public function winning_a_deal_requires_an_order_date_and_losing_it_clears_the_date(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $items = [['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 1100]];

        // 受注日なしで受注にはできない
        $this->from(route('deals.create'))
            ->post(route('deals.store'), $this->payload($items, ['status' => DealStatus::Won->value]))
            ->assertSessionHasErrors('ordered_at');

        // 受注日を入れれば登録できる
        $this->post(route('deals.store'), $this->payload($items, [
            'status' => DealStatus::Won->value,
            'ordered_at' => '2026-08-01',
        ]))->assertSessionHasNoErrors();

        $deal = Deal::query()->sole();

        $this->assertSame(DealStatus::Won, $deal->status);
        $this->assertSame('2026-08-01', $deal->ordered_at?->toDateString());

        // 失注に変えると受注日は消える
        $this->put(route('deals.update', $deal->id), $this->payload(
            [['id' => $deal->items()->sole()->id, 'product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 1100]],
            ['status' => DealStatus::Lost->value, 'probability' => 0, 'ordered_at' => '2026-08-01'],
        ))->assertSessionHasNoErrors();

        $deal->refresh();

        $this->assertSame(DealStatus::Lost, $deal->status);
        $this->assertNull($deal->ordered_at);
    }

    #[Test]
    public function invalid_input_is_rejected(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->from(route('deals.create'))
            ->post(route('deals.store'), $this->payload([
                ['product_id' => $this->standardProduct->id, 'quantity' => 0, 'unit_price' => -1],
            ], [
                'title' => '',
                'probability' => 120,
                'expected_close_date' => '',
            ]))
            ->assertSessionHasErrors([
                'title',
                'probability',
                'expected_close_date',
                'items.0.quantity',
                'items.0.unit_price',
            ]);

        $this->assertSame(0, Deal::query()->count());
    }

    #[Test]
    public function a_contact_of_another_customer_cannot_be_selected(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $otherCustomer = Partner::factory()->create(['partner_type' => PartnerType::Customer]);
        $otherContact = PartnerContact::factory()->create(['partner_id' => $otherCustomer->id]);

        $this->from(route('deals.create'))
            ->post(route('deals.store'), $this->payload([], ['partner_contact_id' => $otherContact->id]))
            ->assertSessionHasErrors('partner_contact_id');
    }

    #[Test]
    public function the_deal_form_is_only_available_to_users_who_can_manage(): void
    {
        $customer = $this->customer;

        $this->actingAsRole(RoleName::Staff);
        $this->get(route('deals.create', ['customer' => $customer->id]))->assertOk();

        $this->post(route('deals.store'), $this->payload([
            ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 1100],
        ]))->assertSessionHasNoErrors();

        $deal = Deal::query()->sole();
        $this->get(route('deals.edit', $deal->id))->assertOk()->assertSee('保守サービス');

        $this->actingAsRole(RoleName::Viewer);
        $this->get(route('deals.create'))->assertForbidden();
        $this->get(route('deals.edit', $deal->id))->assertForbidden();
        $this->post(route('deals.store'), $this->payload())->assertForbidden();
        $this->put(route('deals.update', $deal->id), $this->payload())->assertForbidden();
    }

    #[Test]
    public function deal_changes_are_recorded_in_the_activity_log(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->post(route('deals.store'), $this->payload([
            ['product_id' => $this->standardProduct->id, 'quantity' => 1, 'unit_price' => 1100],
        ]))->assertSessionHasNoErrors();

        $deal = Deal::query()->sole();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'subject_type' => Deal::class,
            'subject_id' => $deal->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'subject_type' => DealItem::class,
        ]);
    }
}
