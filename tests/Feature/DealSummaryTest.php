<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\User;
use App\Support\Crm\DealListSummary;
use App\Support\DataTable\TableBuilder;
use App\Support\DataTable\TableState;
use App\Tables\DealTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商談一覧の上部サマリ(3-E)の検証。
 *
 * 件数・税込合計・加重見込み・ステータス別の内訳が、
 * 絞り込み後の結果に連動し、かつ 1 クエリで取れていることを見る。
 */
class DealSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Partner::factory()->create([
            'name' => 'アオイ商事株式会社',
            'partner_type' => PartnerType::Customer,
        ]);

        $this->employee = Employee::factory()->create(['name' => '営業 太郎']);
    }

    private function actingAsStaff(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);

        $this->actingAs($user);

        return $user;
    }

    private function deal(DealStatus $status, int $amount, int $probability, string $title): Deal
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
        $this->deal(DealStatus::Won, 600000, 100, '受注した案件');
        $this->deal(DealStatus::Proposing, 300000, 50, '提案中の案件');
        $this->deal(DealStatus::Prospect, 100000, 10, '見込みの案件');
    }

    /**
     * 画面と同じ経路でサマリを組み立てる。
     *
     * @param  array<string, string>  $query
     */
    private function summaryFor(array $query = []): DealListSummary
    {
        $request = Request::create('/deals', 'GET', $query);
        $request->setLaravelSession($this->app['session.store']);

        $definition = new DealTable;
        $state = TableState::resolve($request, $definition, false);

        return DealListSummary::for(new TableBuilder($definition, $state));
    }

    #[Test]
    public function it_counts_totals_and_the_weighted_forecast(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        $summary = $this->summaryFor();

        $this->assertSame(3, $summary->dealCount);
        $this->assertSame(1000000, $summary->totalInclTax);
        $this->assertSame(600000, $summary->wonTotal);
        $this->assertSame(400000, $summary->openTotal);
        // 加重見込み = 300,000 × 50% + 100,000 × 10%
        $this->assertSame(160000, $summary->weightedOpenTotal);
        $this->assertSame(333333, $summary->averageInclTax());
    }

    #[Test]
    public function it_breaks_the_result_down_by_status(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        $summary = $this->summaryFor();

        $this->assertSame(
            ['count' => 1, 'amount' => 600000],
            $summary->byStatus[DealStatus::Won->value],
        );
        $this->assertSame(
            ['count' => 1, 'amount' => 300000],
            $summary->byStatus[DealStatus::Proposing->value],
        );
        $this->assertSame(
            ['count' => 0, 'amount' => 0],
            $summary->byStatus[DealStatus::Lost->value],
        );

        // 構成比バーに渡す形(全ステータスぶん、色つき)
        $segments = $summary->statusSegments('amount');
        $this->assertCount(count(DealStatus::cases()), $segments);
        $this->assertSame('受注', $segments[3]['label']);
        $this->assertSame(600000, $segments[3]['value']);
        $this->assertSame('bg-emerald-500', $segments[3]['class']);

        // 件数でも同じ内訳が取れる
        $this->assertSame(1, $summary->statusSegments('count')[3]['value']);

        // 内訳の合計は全体と一致する
        $this->assertSame($summary->totalInclTax, array_sum(array_column($segments, 'value')));
    }

    #[Test]
    public function the_breakdown_follows_the_filters(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        // 進行中だけに絞ると、受注は内訳から消える
        $summary = $this->summaryFor(['status' => 'open']);

        $this->assertSame(2, $summary->dealCount);
        $this->assertSame(400000, $summary->totalInclTax);
        $this->assertSame(0, $summary->byStatus[DealStatus::Won->value]['amount']);
        $this->assertSame(300000, $summary->byStatus[DealStatus::Proposing->value]['amount']);
    }

    #[Test]
    public function the_breakdown_follows_the_period_filter(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        // 受注日を基準にすると、受注済みの 1 件だけが残る
        $summary = $this->summaryFor([
            'period_basis' => 'ordered_at',
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'period_preset' => 'custom',
        ]);

        $this->assertSame(1, $summary->dealCount);
        $this->assertSame(600000, $summary->byStatus[DealStatus::Won->value]['amount']);
        $this->assertSame(0, $summary->byStatus[DealStatus::Proposing->value]['amount']);
    }

    #[Test]
    public function the_summary_is_still_one_query(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        // 絞り込みの選択肢などは先に読み終えておき、集計そのものだけを測る
        $request = Request::create('/deals', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        $definition = new DealTable;
        $builder = new TableBuilder($definition, TableState::resolve($request, $definition, false));

        DB::flushQueryLog();
        DB::enableQueryLog();

        DealListSummary::for($builder);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries, 'サマリはステータス別の内訳を足しても 1 クエリ。');
    }

    #[Test]
    public function the_list_shows_the_summary_and_the_breakdown_bar(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        $response = $this->get(route('deals.index', ['reset' => 1]))->assertOk();

        $response->assertSee('件数')
            ->assertSee('合計(税込)')
            ->assertSee('加重見込み(税込)')
            ->assertSee('ステータス別の内訳')
            ->assertSee('1,000,000')   // 税込合計
            ->assertSee('160,000')     // 加重見込み
            ->assertSee('333,333');    // 平均

        $html = $response->getContent();

        // 構成比バー(受注 600,000 / 1,000,000 = 60%)
        $this->assertStringContainsString('bg-emerald-500', $html);
        $this->assertStringContainsString('width: 60%', $html);
        $this->assertStringContainsString('width: 30%', $html);

        // 金額 / 件数の切り替え
        $this->assertStringContainsString("measure = 'count'", $html);
    }

    #[Test]
    public function the_bar_says_when_nothing_matches(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        $this->get(route('deals.index', ['q' => '該当しないキーワード']))
            ->assertOk()
            ->assertSee('表示中の商談がありません。');
    }
}
