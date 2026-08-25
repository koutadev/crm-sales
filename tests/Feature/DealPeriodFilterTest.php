<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商談一覧の期間フィルタ(3-B)の検証。
 *
 * 「予定クローズ日」「受注日」のどちらを基準にするか選べること、
 * 相対プリセットが常に現在日から計算されること、
 * そして絞り込みが一覧・サマリ・CSV のすべてに同じように効くことを見る。
 */
class DealPeriodFilterTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // 月末・年度の境目に左右されないよう、日付を固定して確かめる
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        $this->customer = Partner::factory()->create([
            'name' => 'テスト商事株式会社',
            'partner_type' => PartnerType::Customer,
        ]);

        $this->employee = Employee::factory()->create(['name' => '営業 太郎']);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function deal(string $title, string $expectedCloseDate, ?string $orderedAt, int $amount): Deal
    {
        return Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'title' => $title,
            'status' => $orderedAt === null ? DealStatus::Proposing : DealStatus::Won,
            'probability' => $orderedAt === null ? 50 : 100,
            'amount_total' => $amount,
            'expected_close_date' => $expectedCloseDate,
            'ordered_at' => $orderedAt,
        ]);
    }

    /**
     * 予定クローズ日は今月・受注日は先月、という商談を用意する。
     * 基準日を切り替えると結果が入れ替わるので、切り替えの検証に使える。
     */
    private function seedTwoDeals(): void
    {
        $this->deal('今月クローズ予定の案件', '2026-08-20', '2026-07-10', 330000);
        $this->deal('来月クローズ予定の案件', '2026-09-05', '2026-08-03', 220000);
    }

    #[Test]
    public function the_list_shows_both_dates(): void
    {
        $this->deal('受注済みの案件', '2026-08-20', '2026-08-12', 330000);
        $this->deal('進行中の案件', '2026-08-25', null, 220000);

        $response = $this->get(route('deals.index', ['reset' => 1]))->assertOk();

        $response->assertSeeInOrder(['予定クローズ日', '受注日']);
        $response->assertSee('2026/08/12');   // 受注日
        $response->assertSee('2026/08/25');   // 予定クローズ日(受注日は空欄)
    }

    #[Test]
    public function it_filters_by_the_expected_close_date(): void
    {
        $this->seedTwoDeals();

        $this->get(route('deals.index', ['period_preset' => 'this_month']))
            ->assertOk()
            ->assertSee('今月クローズ予定の案件')
            ->assertDontSee('来月クローズ予定の案件')
            // サマリも期間適用後の集計になる
            ->assertSee('330,000')
            ->assertDontSee('550,000');
    }

    #[Test]
    public function the_basis_date_can_be_switched_to_the_order_date(): void
    {
        $this->seedTwoDeals();

        // 同じ「今月」でも、受注日を基準にすると結果が入れ替わる
        $this->get(route('deals.index', [
            'period_basis' => 'ordered_at',
            'period_preset' => 'this_month',
        ]))
            ->assertOk()
            ->assertSee('来月クローズ予定の案件')
            ->assertDontSee('今月クローズ予定の案件')
            ->assertSee('220,000');
    }

    #[Test]
    public function a_custom_range_filters_by_the_given_days(): void
    {
        $this->seedTwoDeals();

        $this->get(route('deals.index', [
            'period_preset' => 'custom',
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
        ]))
            ->assertOk()
            ->assertSee('来月クローズ予定の案件')
            ->assertDontSee('今月クローズ予定の案件');
    }

    #[Test]
    public function a_relative_preset_is_recalculated_from_today(): void
    {
        $this->seedTwoDeals();

        $this->get(route('deals.index', ['period_preset' => 'this_month']))
            ->assertOk()
            ->assertSee('今月クローズ予定の案件');

        // 月が替わっても指定し直す必要はない(保存されているのはキーだけ)
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));

        $this->get(route('deals.index'))
            ->assertOk()
            ->assertSee('来月クローズ予定の案件')
            ->assertDontSee('今月クローズ予定の案件');
    }

    #[Test]
    public function the_period_survives_navigating_away_and_back(): void
    {
        $this->seedTwoDeals();

        $this->get(route('deals.index', [
            'period_basis' => 'ordered_at',
            'period_preset' => 'this_month',
        ]))->assertOk();

        // 別の画面を挟んでも、他の絞り込みと同じように前回の条件が残る
        $this->get(route('customers.index', ['reset' => 1]))->assertOk();

        $this->get(route('deals.index'))
            ->assertOk()
            ->assertSee('来月クローズ予定の案件')
            ->assertDontSee('今月クローズ予定の案件');
    }

    #[Test]
    public function the_period_can_be_cleared(): void
    {
        $this->seedTwoDeals();

        $this->get(route('deals.index', ['period_preset' => 'this_month']))->assertOk();

        $this->get(route('deals.index', ['period_preset' => 'none']))
            ->assertOk()
            ->assertSee('今月クローズ予定の案件')
            ->assertSee('来月クローズ予定の案件')
            ->assertSee('550,000');
    }

    #[Test]
    public function the_period_also_applies_to_the_csv(): void
    {
        $this->seedTwoDeals();

        $csv = $this->get(route('deals.export', [
            'period_preset' => 'this_month',
        ]))->assertOk()->streamedContent();

        $this->assertStringContainsString('"予定クローズ日","受注日"', $csv);
        $this->assertStringContainsString('今月クローズ予定の案件', $csv);
        $this->assertStringNotContainsString('来月クローズ予定の案件', $csv);
        $this->assertStringContainsString('"2026/08/20","2026/07/10"', $csv);
    }

    #[Test]
    public function it_can_be_combined_with_the_other_conditions(): void
    {
        $this->seedTwoDeals();
        $this->deal('今月クローズ予定の別案件', '2026-08-21', null, 110000);

        $this->get(route('deals.index', [
            'period_preset' => 'this_month',
            'status' => DealStatus::Won->value,
        ]))
            ->assertOk()
            ->assertSee('今月クローズ予定の案件')
            ->assertDontSee('今月クローズ予定の別案件')
            ->assertDontSee('来月クローズ予定の案件');
    }

    #[Test]
    public function an_unknown_basis_falls_back_to_the_expected_close_date(): void
    {
        $this->seedTwoDeals();

        $this->get(route('deals.index', [
            'period_basis' => 'deleted_at; drop table deals',
            'period_preset' => 'this_month',
        ]))
            ->assertOk()
            ->assertSee('今月クローズ予定の案件')
            ->assertDontSee('来月クローズ予定の案件');
    }
}
