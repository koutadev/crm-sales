<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\DealStatus;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商談一覧(絞り込みに連動する金額サマリ)と商談詳細(金額内訳・活動履歴)の検証。
 */
class DealScreenTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Partner::factory()->create([
            'name' => 'テスト商事株式会社',
            'partner_type' => PartnerType::Customer,
        ]);

        $this->employee = Employee::factory()->create(['name' => '営業 太郎']);
    }

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    private function deal(DealStatus $status, int $amount, int $probability, string $title = '案件'): Deal
    {
        return Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'title' => $title,
            'status' => $status,
            'probability' => $probability,
            'amount_total' => $amount,
            'ordered_at' => $status === DealStatus::Won ? now()->toDateString() : null,
        ]);
    }

    #[Test]
    public function the_list_shows_a_summary_of_the_displayed_deals(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->deal(DealStatus::Won, 330000, 100, '受注した案件');
        $this->deal(DealStatus::Prospect, 220000, 50, '見込みの案件');
        $this->deal(DealStatus::Lost, 110000, 0, '失注した案件');

        $this->get(route('deals.index', ['reset' => 1]))
            ->assertOk()
            ->assertSee('受注した案件')
            ->assertSee('660,000')   // 表示中の合計(税込) = 330,000 + 220,000 + 110,000
            ->assertSee('330,000')   // 受注済み
            ->assertSee('220,000')   // 進行中
            ->assertSee('110,000');  // 加重見込み = 220,000 × 50%
    }

    #[Test]
    public function the_summary_follows_the_filter(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->deal(DealStatus::Won, 330000, 100, '受注した案件');
        $this->deal(DealStatus::Prospect, 220000, 50, '見込みの案件');

        // 受注だけに絞ると、サマリも受注分だけになる
        $this->get(route('deals.index', ['status' => DealStatus::Won->value]))
            ->assertOk()
            ->assertSee('受注した案件')
            ->assertDontSee('見込みの案件')
            ->assertDontSee('220,000');

        // 絞り込みを外せば元に戻る
        $this->get(route('deals.index', ['reset' => 1]))
            ->assertOk()
            ->assertSee('550,000');
    }

    #[Test]
    public function the_list_summary_does_not_grow_with_the_number_of_deals(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->createDeals(3);
        $this->get(route('deals.index', ['reset' => 1]))->assertOk();   // キャッシュを温める

        $baseline = $this->countQueries(fn () => $this->get(route('deals.index'))->assertOk());

        $this->createDeals(12);

        $scaled = $this->countQueries(fn () => $this->get(route('deals.index'))->assertOk());

        $this->assertSame($baseline, $scaled, '商談が増えてもクエリ本数は変わらない(サマリは 1 クエリ)。');
    }

    #[Test]
    public function the_csv_contains_the_amount_breakdown(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $deal = $this->dealWithItems();

        $csv = $this->get(route('deals.export', ['reset' => 1]))->assertOk()->streamedContent();

        $this->assertStringContainsString('"金額(税込)","消費税","税抜"', $csv);
        // 税込 11,880 / 消費税 1,061(981 + 80) / 税抜 10,819
        $this->assertStringContainsString('"'.$deal->code.'"', $csv);
        $this->assertStringContainsString('"11880","1061","10819"', $csv);
    }

    #[Test]
    public function the_detail_shows_the_amounts_broken_down_by_tax_rate(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $deal = $this->dealWithItems();

        $this->get(route('deals.show', $deal->id))
            ->assertOk()
            ->assertSee($deal->code)
            ->assertSee('テスト商事株式会社')
            // 合計(税込 / 消費税 / 税抜)
            ->assertSee('11,880')
            ->assertSee('1,061')
            ->assertSee('10,819')
            // 税率別の内訳
            ->assertSee('10% 対象（税込）', false)
            ->assertSee('8% 対象（税込）', false)
            ->assertSee('10,800')
            ->assertSee('981')
            ->assertSee('1,080')
            ->assertSee('80');
    }

    #[Test]
    public function an_activity_can_be_added_from_the_deal_detail(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $deal = $this->deal(DealStatus::Proposing, 0, 40);

        $this->post(route('deals.activities.store', $deal->id), [
            'employee_id' => $this->employee->id,
            'type' => ActivityType::Visit->value,
            'activity_at' => '2026-08-20 10:30',
            'note' => '訪問して要件を確認した。',
        ])->assertRedirect(route('deals.show', $deal->id));

        $activity = Activity::query()->sole();

        $this->assertSame($deal->id, $activity->deal_id);
        $this->assertSame($this->customer->id, $activity->partner_id, '活動は顧客にも紐づく。');
        $this->assertSame(ActivityType::Visit, $activity->type);
        $this->assertSame('2026-08-20 10:30', $activity->activity_at->format('Y-m-d H:i'));

        $this->get(route('deals.show', $deal->id))
            ->assertOk()
            ->assertSee('訪問して要件を確認した。');
    }

    #[Test]
    public function activities_are_listed_newest_first(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $deal = $this->deal(DealStatus::Proposing, 0, 40);

        Activity::factory()->create([
            'partner_id' => $this->customer->id,
            'deal_id' => $deal->id,
            'employee_id' => $this->employee->id,
            'activity_at' => '2026-08-01 09:00',
            'note' => '古い活動',
        ]);

        Activity::factory()->create([
            'partner_id' => $this->customer->id,
            'deal_id' => $deal->id,
            'employee_id' => $this->employee->id,
            'activity_at' => '2026-08-20 09:00',
            'note' => '新しい活動',
        ]);

        $this->get(route('deals.show', $deal->id))
            ->assertOk()
            ->assertSeeInOrder(['新しい活動', '古い活動']);
    }

    #[Test]
    public function activity_input_is_validated(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $deal = $this->deal(DealStatus::Proposing, 0, 40);

        $this->from(route('deals.show', $deal->id))
            ->post(route('deals.activities.store', $deal->id), [
                'employee_id' => '',
                'type' => 'unknown',
                'activity_at' => '',
            ])
            ->assertSessionHasErrors(['employee_id', 'type', 'activity_at']);

        $this->assertSame(0, Activity::query()->count());
    }

    #[Test]
    public function a_viewer_can_browse_but_cannot_add_activities(): void
    {
        $this->actingAsRole(RoleName::Viewer);

        $deal = $this->deal(DealStatus::Proposing, 100000, 40);

        $this->get(route('deals.index', ['reset' => 1]))->assertOk();
        $this->get(route('deals.show', $deal->id))->assertOk()->assertDontSee('活動を追加');
        $this->get(route('deals.export'))->assertOk();

        $this->post(route('deals.activities.store', $deal->id), [
            'employee_id' => $this->employee->id,
            'type' => ActivityType::Phone->value,
            'activity_at' => '2026-08-20 10:00',
        ])->assertForbidden();

        $this->delete(route('deals.destroy', $deal->id))->assertForbidden();
    }

    #[Test]
    public function a_non_numeric_id_is_rejected_by_validation_not_by_the_database(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $deal = $this->deal(DealStatus::Proposing, 0, 40);

        // 数値でない ID をそのまま exists に渡すと PostgreSQL が型エラーを投げる。
        // bail + integer で、バリデーションエラーとして返ることを確かめる
        $this->from(route('deals.show', $deal->id))
            ->post(route('deals.activities.store', $deal->id), [
                'employee_id' => 'not-a-number',
                'type' => ActivityType::Phone->value,
                'activity_at' => '2026-08-20 10:00',
            ])
            ->assertRedirect(route('deals.show', $deal->id))
            ->assertSessionHasErrors('employee_id');
    }

    /**
     * 10% と 8% が混ざった明細を持つ商談。
     */
    private function dealWithItems(): Deal
    {
        $standardRate = TaxRate::factory()->create(['name' => '標準', 'rate_percent' => 10]);
        $reducedRate = TaxRate::factory()->reduced()->create();

        $deal = $this->deal(DealStatus::Proposing, 0, 40, '内訳の確認案件');

        DealItem::factory()->create([
            'deal_id' => $deal->id,
            'product_id' => Product::factory()->create(['tax_rate_id' => $standardRate->id])->id,
            'tax_rate_id' => $standardRate->id,
            'tax_rate_percent' => 10,
            'quantity' => 1,
            'unit_price' => 10800,
        ]);

        DealItem::factory()->create([
            'deal_id' => $deal->id,
            'product_id' => Product::factory()->create(['tax_rate_id' => $reducedRate->id])->id,
            'tax_rate_id' => $reducedRate->id,
            'tax_rate_percent' => 8,
            'quantity' => 1,
            'unit_price' => 1080,
        ]);

        return $deal->refresh();
    }

    private function createDeals(int $count): void
    {
        Deal::factory()->count($count)->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'amount_total' => 100000,
        ]);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }
}
