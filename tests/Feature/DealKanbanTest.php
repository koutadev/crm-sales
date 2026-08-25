<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\User;
use App\Support\Crm\DealKanban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商談のカンバン(パイプライン)表示(3-F)の検証。
 *
 * 一覧と同じ絞り込み・同じ集計のまま、ステータスを列にして並べる。
 * カードのドラッグでのステータス変更は、受注日の扱いを登録・編集画面とそろえる。
 */
class DealKanbanTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        $this->customer = Partner::factory()->create([
            'name' => 'アオイ商事株式会社',
            'partner_type' => PartnerType::Customer,
        ]);

        $this->employee = Employee::factory()->create(['name' => '営業 太郎']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($role === RoleName::Viewer ? $user : $user);

        return $user;
    }

    private function deal(DealStatus $status, int $amount, string $title, int $probability = 50): Deal
    {
        return Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'title' => $title,
            'status' => $status,
            'probability' => $probability,
            'amount_total' => $amount,
            'expected_close_date' => '2026-08-20',
            'ordered_at' => $status === DealStatus::Won ? '2026-08-10' : null,
        ]);
    }

    private function seedDeals(): void
    {
        $this->deal(DealStatus::Won, 600000, '受注した案件', 100);
        $this->deal(DealStatus::Proposing, 300000, '提案中の案件');
        $this->deal(DealStatus::Prospect, 100000, '見込みの案件', 10);
    }

    /**
     * 明細を 1 件だけ持つ商談(受注にできる状態)。
     */
    private function dealWithItem(DealStatus $status): Deal
    {
        $deal = $this->deal($status, 11000, '明細のある案件');

        // 明細の金額はファクトリ側の規則(税込が正)に任せる
        DealItem::factory()->create(['deal_id' => $deal->id]);

        return $deal->fresh() ?? $deal;
    }

    #[Test]
    public function the_board_shows_one_lane_per_status_with_its_totals(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $this->seedDeals();

        $html = $this->get(route('deals.index', ['view_mode' => 'kanban']))->assertOk()->getContent();

        // 5 つの列がステータス順に並ぶ
        foreach (DealStatus::cases() as $status) {
            $this->assertStringContainsString('data-status="'.$status->value.'"', $html);
        }

        // 列ヘッダーの件数と税込金額
        $this->assertStringContainsString('600,000', $html);
        $this->assertStringContainsString('300,000', $html);

        // カードの要点と、詳細へのリンク
        $this->assertStringContainsString('受注した案件', $html);
        $this->assertStringContainsString('アオイ商事株式会社', $html);
        $this->assertStringContainsString('確度 10%', $html);
        $this->assertStringContainsString('2026/08/20', $html);
        $this->assertStringContainsString('営業 太郎', $html);
        $this->assertStringContainsString(route('deals.show', Deal::query()->firstOrFail()->id), $html);
    }

    #[Test]
    public function the_view_can_be_switched_and_is_remembered(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $this->seedDeals();

        // 表 → カンバン
        $this->get(route('deals.index', ['view_mode' => 'kanban']))
            ->assertOk()
            ->assertSee('data-status="won"', false);

        // 条件を指定せず開き直してもカンバンのまま
        $this->get(route('deals.index'))
            ->assertOk()
            ->assertSee('data-status="won"', false);

        // 表に戻せる
        $this->get(route('deals.index', ['view_mode' => 'table']))
            ->assertOk()
            ->assertDontSee('data-status="won"', false);
    }

    #[Test]
    public function the_filters_apply_to_the_board(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $this->seedDeals();

        // 進行中だけに絞ると、受注の列は 0 件になる
        $html = $this->get(route('deals.index', ['view_mode' => 'kanban', 'status' => 'open']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('受注した案件', $html);
        $this->assertStringContainsString('提案中の案件', $html);

        // 確度でも絞れる
        $html = $this->get(route('deals.index', ['view_mode' => 'kanban', 'probability_min' => '50']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('見込みの案件', $html);
        $this->assertStringContainsString('提案中の案件', $html);
    }

    #[Test]
    public function each_lane_is_capped_and_says_how_many_are_hidden(): void
    {
        $this->actingAsRole(RoleName::Staff);

        Deal::factory()->count(DealKanban::LANE_LIMIT + 3)->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'status' => DealStatus::Prospect,
            'amount_total' => 1000,
            'expected_close_date' => '2026-08-20',
        ]);

        $html = $this->get(route('deals.index', ['view_mode' => 'kanban']))->assertOk()->getContent();

        $this->assertSame(DealKanban::LANE_LIMIT, substr_count($html, 'data-deal-id='));
        $this->assertStringContainsString('他 3 件', $html);
    }

    #[Test]
    public function the_board_does_not_run_more_queries_as_deals_grow(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $this->seedDeals();

        $this->get(route('deals.index', ['view_mode' => 'kanban']))->assertOk();   // キャッシュを温める

        $baseline = $this->countQueries(
            fn () => $this->get(route('deals.index', ['view_mode' => 'kanban']))->assertOk()
        );

        Deal::factory()->count(12)->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'status' => DealStatus::Quoted,
            'amount_total' => 5000,
            'expected_close_date' => '2026-08-20',
        ]);

        $scaled = $this->countQueries(
            fn () => $this->get(route('deals.index', ['view_mode' => 'kanban']))->assertOk()
        );

        $this->assertSame($baseline, $scaled, '商談が増えてもカンバンのクエリ本数は変わらない。');
        $this->assertLessThanOrEqual(10, $scaled, 'カンバンのクエリ本数は一覧と同程度に収める。');
    }

    #[Test]
    public function dragging_a_card_changes_the_status(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $deal = $this->deal(DealStatus::Prospect, 100000, '動かす案件');

        $this->patchJson(route('deals.status.update', $deal->id), ['status' => DealStatus::Quoted->value])
            ->assertOk()
            ->assertJsonPath('message', '商談 '.$deal->code.' を「見積提示」に変更しました。');

        $this->assertSame(DealStatus::Quoted, $deal->fresh()?->status);
    }

    #[Test]
    public function moving_to_won_records_the_order_date_and_moving_back_clears_it(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $deal = $this->dealWithItem(DealStatus::Quoted);

        $this->patchJson(route('deals.status.update', $deal->id), ['status' => DealStatus::Won->value])
            ->assertOk();

        $deal->refresh();
        $this->assertSame('2026-08-15', $deal->ordered_at?->toDateString());

        // 受注から出すと受注日は消える(登録・編集画面と同じ規則)
        $this->patchJson(route('deals.status.update', $deal->id), ['status' => DealStatus::Lost->value])
            ->assertOk();

        $deal->refresh();
        $this->assertNull($deal->ordered_at);
    }

    #[Test]
    public function a_deal_without_items_cannot_be_won_by_dragging(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $deal = $this->deal(DealStatus::Quoted, 0, '明細のない案件');

        $this->patchJson(route('deals.status.update', $deal->id), ['status' => DealStatus::Won->value])
            ->assertStatus(422)
            ->assertJsonPath('message', '受注にするには、明細を 1 件以上登録してください。');

        $this->assertSame(DealStatus::Quoted, $deal->fresh()?->status);
    }

    #[Test]
    public function the_amount_is_not_touched_by_a_status_change(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $deal = $this->dealWithItem(DealStatus::Quoted);
        $before = $deal->amount_total;

        $this->patchJson(route('deals.status.update', $deal->id), ['status' => DealStatus::Won->value])
            ->assertOk();

        $deal->refresh();

        $this->assertSame($before, $deal->amount_total);
    }

    #[Test]
    public function an_unknown_status_is_rejected(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $deal = $this->deal(DealStatus::Prospect, 1000, '案件');

        $this->patchJson(route('deals.status.update', $deal->id), ['status' => 'archived'])
            ->assertStatus(422);
    }

    #[Test]
    public function a_viewer_can_see_the_board_but_cannot_move_cards(): void
    {
        $this->actingAsRole(RoleName::Viewer);
        $deal = $this->deal(DealStatus::Prospect, 1000, '見るだけの案件');

        $html = $this->get(route('deals.index', ['view_mode' => 'kanban']))->assertOk()->getContent();

        $this->assertStringContainsString('見るだけの案件', $html);
        $this->assertStringNotContainsString('draggable="true"', $html);

        $this->patchJson(route('deals.status.update', $deal->id), ['status' => DealStatus::Won->value])
            ->assertForbidden();
    }

    /**
     * 1 リクエストで走ったクエリの本数。
     */
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
