<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\OrganizationType;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Enums\TargetScope;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\SalesTarget;
use App\Models\User;
use App\Support\Crm\OrganizationSales;
use App\Support\Crm\SalesTargetLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 予実（目標と実績）の検証。
 *
 * 実績は既存の受注集計をそのまま使い、達成率は「実績 ÷ 目標」で出す。
 * 目標が無い場合は達成率を出さない。
 */
class TargetProgressTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    private Organization $tokyo;

    private Organization $osaka;

    private Employee $tokyoRep;

    private Employee $osakaRep;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-20 10:00:00'));

        $this->customer = Partner::factory()->create(['partner_type' => PartnerType::Customer]);

        $east = Organization::create(['name' => '東日本地域', 'type' => OrganizationType::Region, 'is_active' => true]);
        $capital = Organization::create(['name' => '首都圏エリア', 'type' => OrganizationType::Area, 'parent_id' => $east->id, 'is_active' => true]);
        $this->tokyo = Organization::create(['name' => '東京本店', 'type' => OrganizationType::Store, 'parent_id' => $capital->id, 'prefecture' => '東京都', 'is_active' => true]);

        $west = Organization::create(['name' => '西日本地域', 'type' => OrganizationType::Region, 'is_active' => true]);
        $kansai = Organization::create(['name' => '関西エリア', 'type' => OrganizationType::Area, 'parent_id' => $west->id, 'is_active' => true]);
        $this->osaka = Organization::create(['name' => '大阪本店', 'type' => OrganizationType::Store, 'parent_id' => $kansai->id, 'prefecture' => '大阪府', 'is_active' => true]);

        $this->tokyoRep = Employee::factory()->create(['name' => '東京 太郎', 'organization_id' => $this->tokyo->id]);
        $this->osakaRep = Employee::factory()->create(['name' => '大阪 花子', 'organization_id' => $this->osaka->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function wonDeal(Employee $employee, int $amount, string $orderedAt): void
    {
        Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $employee->id,
            'status' => DealStatus::Won,
            'amount_total' => $amount,
            'ordered_at' => $orderedAt,
        ]);
    }

    private function target(TargetScope $scope, ?int $targetId, int $year, int $month, int $amount): SalesTarget
    {
        return SalesTarget::create([
            'scope' => $scope,
            'target_id' => $targetId,
            'year' => $year,
            'month' => $month,
            'amount' => $amount,
            'is_active' => true,
        ]);
    }

    private function build(): OrganizationSales
    {
        $fiscalStart = Carbon::parse('2026-04-01');
        $fiscalEnd = Carbon::parse('2027-03-31');

        return OrganizationSales::build(
            Carbon::now(),
            SalesTargetLookup::build(Carbon::now(), $fiscalStart, $fiscalEnd),
            $fiscalStart,
            $fiscalEnd,
        );
    }

    #[Test]
    public function the_achievement_rate_is_actual_over_target(): void
    {
        // 当月の実績
        $this->wonDeal($this->tokyoRep, 1200000, '2026-08-10');
        $this->wonDeal($this->osakaRep, 400000, '2026-08-15');
        // 先月の実績（当月には数えない）
        $this->wonDeal($this->tokyoRep, 5000000, '2026-07-10');

        $this->target(TargetScope::Company, null, 2026, 8, 2000000);
        $this->target(TargetScope::Store, $this->tokyo->id, 2026, 8, 1000000);
        $this->target(TargetScope::Store, $this->osaka->id, 2026, 8, 1000000);
        $this->target(TargetScope::Employee, $this->tokyoRep->id, 2026, 8, 800000);

        $sales = $this->build();

        // 全社
        $this->assertSame(1600000, $sales->monthAmount);
        $this->assertSame(2000000, $sales->monthTarget);
        $this->assertSame(80.0, $sales->achievement()->rate());
        $this->assertSame('達成間近', $sales->achievement()->label());

        // 累計は当月に限らない
        $this->assertSame(6600000, $sales->totalInclTax);

        // 店舗（東京は 120%、大阪は 40%）
        $stores = [];

        foreach ($sales->regions as $region) {
            foreach ($region->children as $area) {
                foreach ($area->children as $store) {
                    $stores[$store->name] = $store;
                }
            }
        }

        $this->assertSame(120.0, $stores['東京本店']->achievement()->rate());
        $this->assertTrue($stores['東京本店']->achievement()->isAchieved());
        $this->assertSame(40.0, $stores['大阪本店']->achievement()->rate());

        // 担当者
        $tokyoMember = $stores['東京本店']->children[0];
        $this->assertSame('東京 太郎', $tokyoMember->name);
        $this->assertSame(150.0, $tokyoMember->achievement()->rate());

        // 目標が無い担当者は達成率を出さない
        $osakaMember = $stores['大阪本店']->children[0];
        $this->assertFalse($osakaMember->achievement()->hasTarget());
        $this->assertSame('—', $osakaMember->achievement()->rateLabel());
    }

    #[Test]
    public function the_fiscal_year_total_only_counts_the_year(): void
    {
        // 年度内（2026-04〜2027-03）
        $this->wonDeal($this->tokyoRep, 3000000, '2026-05-20');
        $this->wonDeal($this->tokyoRep, 1000000, '2026-08-10');
        // 年度外（前年度）
        $this->wonDeal($this->tokyoRep, 9000000, '2026-03-31');

        $this->target(TargetScope::Company, null, 2026, 5, 2000000);
        $this->target(TargetScope::Company, null, 2026, 8, 2000000);
        // 前年度の目標は年度合計に入れない
        $this->target(TargetScope::Company, null, 2026, 3, 5000000);

        $sales = $this->build();
        $targets = SalesTargetLookup::build(Carbon::now(), Carbon::parse('2026-04-01'), Carbon::parse('2027-03-31'));

        $this->assertSame(4000000, $sales->fiscalAmount);
        $this->assertSame(4000000, $targets->fiscalTotal());
    }

    #[Test]
    public function it_can_be_grouped_by_prefecture(): void
    {
        $this->wonDeal($this->tokyoRep, 1200000, '2026-08-10');
        $this->wonDeal($this->osakaRep, 400000, '2026-08-15');

        $this->target(TargetScope::Store, $this->tokyo->id, 2026, 8, 1000000);

        $sales = $this->build();

        $prefectures = [];

        foreach ($sales->prefectures as $node) {
            $prefectures[$node->name] = $node;
        }

        $this->assertSame(['東京都', '大阪府'], array_keys($prefectures));
        $this->assertSame(1200000, $prefectures['東京都']->monthAmount);
        $this->assertSame(1000000, $prefectures['東京都']->monthTarget);
        $this->assertSame(120.0, $prefectures['東京都']->achievement()->rate());

        // 都道府県の下は店舗
        $this->assertSame('東京本店', $prefectures['東京都']->children[0]->name);
    }

    #[Test]
    public function the_aggregation_stays_at_three_queries(): void
    {
        $this->wonDeal($this->tokyoRep, 1200000, '2026-08-10');
        $this->target(TargetScope::Company, null, 2026, 8, 2000000);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->build();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 目標 1 本 + 実績(担当者ごと) 1 本 + 組織の一覧 1 本
        $this->assertSame(3, $queries);
    }

    #[Test]
    public function the_dashboard_shows_the_gauges_and_the_target_line(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);
        $this->actingAs($user);

        $this->wonDeal($this->tokyoRep, 1600000, '2026-08-10');
        $this->target(TargetScope::Company, null, 2026, 8, 2000000);
        $this->target(TargetScope::Store, $this->tokyo->id, 2026, 8, 1000000);

        $response = $this->get(route('dashboard'))->assertOk();

        $response->assertSee('予実（目標と実績）')
            ->assertSee('当月（2026年8月）')
            ->assertSee('2026年度')
            ->assertSee('80%')
            ->assertSee('達成間近')
            // 月次推移に目標ラインが重なる
            ->assertSee('全社目標')
            // 組織別の表に当月実績・目標・達成率が並ぶ
            ->assertSee('当月実績')
            ->assertSee('当月目標')
            ->assertSee('達成率')
            // 都道府県の切り口
            ->assertSee('集計の切り口')
            ->assertSee('東京都');
    }
}
