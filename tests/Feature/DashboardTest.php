<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\PartnerType;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\User;
use App\Support\Crm\DealMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 売上ダッシュボードの検証。
 *
 * 集計値がシードした商談に対して手計算と一致すること、
 * および集計がループではなく一括クエリで行われていること(クエリ本数)を見る。
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    private Employee $ace;

    private Employee $rookie;

    protected function setUp(): void
    {
        parent::setUp();

        // 「今月」を固定して集計を検証できるようにする
        $this->travelTo(Carbon::parse('2026-08-15 10:00:00'));

        $this->customer = Partner::factory()->create(['partner_type' => PartnerType::Customer]);
        $this->ace = Employee::factory()->create(['name' => '売上 一番']);
        $this->rookie = Employee::factory()->create(['name' => '売上 二番']);
    }

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    /**
     * 検証用の商談。金額・確度・日付をすべて明示する。
     */
    private function deal(
        DealStatus $status,
        int $amount,
        int $probability,
        Employee $employee,
        ?string $orderedAt = null,
        string $expectedCloseDate = '2026-08-31',
    ): Deal {
        return Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $employee->id,
            'status' => $status,
            'amount_total' => $amount,
            'probability' => $probability,
            'ordered_at' => $orderedAt,
            'expected_close_date' => $expectedCloseDate,
        ]);
    }

    /**
     * 検証用のデータ一式を作る。
     */
    private function seedDeals(): void
    {
        // 受注(今月) 330,000 + 550,000 = 880,000
        $this->deal(DealStatus::Won, 330000, 100, $this->ace, '2026-08-05', '2026-08-05');
        $this->deal(DealStatus::Won, 550000, 100, $this->ace, '2026-08-20', '2026-12-01');   // 納品予定は先 = 受注残

        // 受注(先月) 220,000 / 受注(13 か月前) 110,000 … 直近 12 か月の外
        $this->deal(DealStatus::Won, 220000, 100, $this->rookie, '2026-07-10', '2026-07-10');
        $this->deal(DealStatus::Won, 110000, 100, $this->rookie, '2025-06-01', '2025-06-01');

        // 進行中: 今月クローズ予定 200,000 × 50% と、来月クローズ予定 100,000 × 10%
        $this->deal(DealStatus::Proposing, 200000, 50, $this->ace, null, '2026-08-31');
        $this->deal(DealStatus::Prospect, 100000, 10, $this->rookie, null, '2026-09-30');

        // 失注は売上にも見込みにも入らない
        $this->deal(DealStatus::Lost, 999999, 0, $this->ace, null, '2026-08-10');
    }

    #[Test]
    public function the_kpis_match_the_manual_calculation(): void
    {
        $this->seedDeals();

        $headline = DealMetrics::headline();

        // 今月の受注 = 330,000 + 550,000
        $this->assertSame(880000, $headline->wonThisMonth);

        // 今月の受注見込み = 200,000 × 50%(今月クローズ予定のみ)
        $this->assertSame(100000, $headline->forecastThisMonth);

        // 進行中の件数 = 提案中 1 + 見込み 1
        $this->assertSame(2, $headline->openCount);

        // 受注残 = 受注済みで納品予定日が未到来 = 550,000
        $this->assertSame(550000, $headline->backlogTotal);
    }

    #[Test]
    public function the_monthly_sales_are_based_on_the_order_date_and_cover_twelve_months(): void
    {
        $this->seedDeals();

        $monthly = DealMetrics::monthlySales(12);

        $this->assertCount(12, $monthly);
        $this->assertSame('2025/09', array_key_first($monthly));
        $this->assertSame('2026/08', array_key_last($monthly));

        $this->assertSame(880000, $monthly['2026/08']);
        $this->assertSame(220000, $monthly['2026/07']);
        $this->assertSame(0, $monthly['2026/06'], '受注が無い月も 0 で並ぶ。');

        // 13 か月前の受注(110,000)は 12 か月の外なので合計に入らない
        $this->assertSame(1100000, array_sum($monthly));
    }

    #[Test]
    public function the_sales_by_employee_are_ranked(): void
    {
        $this->seedDeals();

        $sales = DealMetrics::salesByEmployee();

        // 売上 一番: 330,000 + 550,000 / 売上 二番: 220,000 + 110,000
        $this->assertSame(['売上 一番' => 880000, '売上 二番' => 330000], $sales);
        $this->assertSame('売上 一番', array_key_first($sales), '多い順に並ぶ。');
    }

    #[Test]
    public function the_pipeline_covers_every_status_with_weighted_amounts(): void
    {
        $this->seedDeals();

        $pipeline = collect(DealMetrics::pipeline())->keyBy(fn ($row) => $row->status->value);

        $this->assertCount(5, $pipeline, '0 件のステータスも並ぶ。');

        $this->assertSame(100000, $pipeline[DealStatus::Prospect->value]->totalInclTax);
        $this->assertSame(10000, $pipeline[DealStatus::Prospect->value]->weightedTotal);

        $this->assertSame(200000, $pipeline[DealStatus::Proposing->value]->totalInclTax);
        $this->assertSame(100000, $pipeline[DealStatus::Proposing->value]->weightedTotal);

        $this->assertSame(0, $pipeline[DealStatus::Quoted->value]->dealCount);

        $this->assertSame(4, $pipeline[DealStatus::Won->value]->dealCount);
        $this->assertSame(1210000, $pipeline[DealStatus::Won->value]->totalInclTax);

        $this->assertSame(999999, $pipeline[DealStatus::Lost->value]->totalInclTax);
        $this->assertSame(0, $pipeline[DealStatus::Lost->value]->weightedTotal);
    }

    #[Test]
    public function the_aggregates_match_a_separate_query(): void
    {
        $this->seedDeals();

        // 別のやり方(素の集計クエリ)でも同じ数字になることを確かめる
        $wonThisMonth = (int) Deal::query()
            ->where('status', DealStatus::Won->value)
            ->whereBetween('ordered_at', ['2026-08-01', '2026-08-31'])
            ->sum('amount_total');

        $byEmployee = (int) Deal::query()
            ->where('status', DealStatus::Won->value)
            ->where('employee_id', $this->ace->id)
            ->sum('amount_total');

        $this->assertSame($wonThisMonth, DealMetrics::headline()->wonThisMonth);
        $this->assertSame($byEmployee, DealMetrics::salesByEmployee()['売上 一番']);
    }

    #[Test]
    public function the_dashboard_shows_the_kpis_and_charts(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $this->seedDeals();

        $response = $this->get(route('dashboard'))->assertOk();

        $response->assertSee('今月の受注(税込)')
            ->assertSee('880,000')
            ->assertSee('100,000')
            ->assertSee('受注残(税込)')
            ->assertSee('550,000')
            ->assertSee('パイプライン（ステータス別）', false);

        $kpis = $response->viewData('kpis');
        $this->assertCount(4, $kpis);
        $this->assertSame(880000, $kpis[0]->value);
        $this->assertSame(2, $kpis[2]->value);

        $charts = $response->viewData('charts');
        $this->assertCount(3, $charts);

        $line = $charts[0]->toChartJs();
        $this->assertSame('line', $line['type']);
        $this->assertCount(12, $line['data']['labels']);

        $bar = $charts[1]->toChartJs();
        $this->assertSame('bar', $bar['type']);
        $this->assertSame(['売上 一番', '売上 二番'], $bar['data']['labels']);

        $response->assertSee('id="chart-monthly-sales"', false);
        $response->assertSee('id="chart-sales-by-employee"', false);
        $response->assertSee('id="chart-pipeline-amount"', false);
    }

    #[Test]
    public function the_aggregation_does_not_run_one_query_per_month_or_per_employee(): void
    {
        $this->actingAsRole(RoleName::Staff);
        $this->seedDeals();

        $this->get(route('dashboard'))->assertOk();   // 権限などのキャッシュを温める

        $baseline = $this->countQueries(fn () => $this->get(route('dashboard'))->assertOk());

        // 商談・担当者・月を増やしてもクエリ本数は変わらないはず
        $employees = Employee::factory()->count(5)->create();

        foreach (range(1, 30) as $index) {
            $this->deal(
                DealStatus::Won,
                10000,
                100,
                $employees->random(),
                Carbon::parse('2026-08-15')->subMonths($index % 12)->format('Y-m-d'),
                '2026-08-31',
            );
        }

        $scaled = $this->countQueries(fn () => $this->get(route('dashboard'))->assertOk());

        $this->assertSame($baseline, $scaled, '月や担当者ごとにクエリを回していない。');
        // 集計は 4 クエリ(KPI 4 種 / 月次推移 / 担当者別 / ステータス別 で各 1 本)。
        // 本数が増えたらループでクエリを回している疑いがあるので、実測値で固定しておく
        $this->assertSame(4, $baseline, 'ダッシュボードの集計クエリは 4 本。');
    }

    #[Test]
    public function a_viewer_can_see_the_sales_figures(): void
    {
        $this->actingAsRole(RoleName::Viewer);
        $this->seedDeals();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('今月の受注(税込)')
            ->assertSee('880,000');
    }

    #[Test]
    public function the_recent_activity_panel_is_limited_to_users_who_may_read_logs(): void
    {
        $this->actingAsRole(RoleName::Admin);

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
    public function a_user_without_master_permission_sees_no_figures(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionName::DashboardView->value);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame([], $response->viewData('kpis'));
        $this->assertSame([], $response->viewData('charts'));
        $this->assertSame([], $response->viewData('pipeline'));
        $response->assertSee('表示できる情報がありません');
    }

    #[Test]
    public function a_chart_without_data_is_reported_as_empty(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $charts = $this->get(route('dashboard'))->viewData('charts');

        $this->assertTrue($charts[0]->isEmpty(), '受注が 0 件ならグラフは空として扱う');
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
