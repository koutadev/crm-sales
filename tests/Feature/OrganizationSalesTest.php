<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\OrganizationType;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\User;
use App\Support\Crm\OrganizationSales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 組織別(地域 > エリア > 店舗 > 担当者)の売上集計の検証。
 *
 * 商談テーブルには手を入れず、担当者の所属をたどって積み上げる。
 * 階層が深くなっても、読むクエリは 2 本のまま。
 */
class OrganizationSalesTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Partner::factory()->create(['partner_type' => PartnerType::Customer]);
    }

    private function actingAsStaff(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);

        $this->actingAs($user);

        return $user;
    }

    private function organization(string $name, OrganizationType $type, ?Organization $parent = null): Organization
    {
        return Organization::create([
            'name' => $name,
            'type' => $type,
            'parent_id' => $parent?->id,
            'is_active' => true,
        ]);
    }

    private function wonDeal(Employee $employee, int $amount): Deal
    {
        return Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $employee->id,
            'status' => DealStatus::Won,
            'amount_total' => $amount,
            'ordered_at' => now()->toDateString(),
        ]);
    }

    /**
     * 東日本(首都圏[東京・横浜] / 北関東[大宮])と、西日本(関西[大阪])。
     *
     * @return array<string, Organization>
     */
    private function tree(): array
    {
        $east = $this->organization('東日本地域', OrganizationType::Region);
        $capital = $this->organization('首都圏エリア', OrganizationType::Area, $east);
        $north = $this->organization('北関東エリア', OrganizationType::Area, $east);
        $west = $this->organization('西日本地域', OrganizationType::Region);
        $kansai = $this->organization('関西エリア', OrganizationType::Area, $west);

        return [
            'tokyo' => $this->organization('東京本店', OrganizationType::Store, $capital),
            'yokohama' => $this->organization('横浜支店', OrganizationType::Store, $capital),
            'omiya' => $this->organization('大宮支店', OrganizationType::Store, $north),
            'osaka' => $this->organization('大阪支店', OrganizationType::Store, $kansai),
        ];
    }

    #[Test]
    public function it_rolls_sales_up_from_employees_to_regions(): void
    {
        $stores = $this->tree();

        $tokyo = Employee::factory()->create(['name' => '東京 太郎', 'organization_id' => $stores['tokyo']->id]);
        $yokohama = Employee::factory()->create(['name' => '横浜 花子', 'organization_id' => $stores['yokohama']->id]);
        $omiya = Employee::factory()->create(['name' => '大宮 次郎', 'organization_id' => $stores['omiya']->id]);
        $osaka = Employee::factory()->create(['name' => '大阪 三郎', 'organization_id' => $stores['osaka']->id]);

        $this->wonDeal($tokyo, 500000);
        $this->wonDeal($tokyo, 300000);
        $this->wonDeal($yokohama, 200000);
        $this->wonDeal($omiya, 100000);
        $this->wonDeal($osaka, 400000);

        // 受注以外は数えない
        Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $tokyo->id,
            'status' => DealStatus::Proposing,
            'amount_total' => 999999,
        ]);

        $sales = OrganizationSales::build();

        $this->assertSame(1500000, $sales->totalInclTax);
        $this->assertSame(5, $sales->dealCount);

        // 地域は金額の多い順
        $east = $sales->regions[0];
        $this->assertSame('東日本地域', $east->name);
        $this->assertSame(1100000, $east->amountInclTax);
        $this->assertSame(4, $east->dealCount);

        // 地域 > エリア > 店舗 > 担当者 と掘り下げられる
        $capital = $east->children[0];
        $this->assertSame('首都圏エリア', $capital->name);
        $this->assertSame(1000000, $capital->amountInclTax);

        $tokyoStore = $capital->children[0];
        $this->assertSame('東京本店', $tokyoStore->name);
        $this->assertSame(800000, $tokyoStore->amountInclTax);
        $this->assertSame(2, $tokyoStore->dealCount);

        $member = $tokyoStore->children[0];
        $this->assertSame('東京 太郎', $member->name);
        $this->assertSame(800000, $member->amountInclTax);
        $this->assertFalse($member->hasChildren());

        // 構成比
        $this->assertSame(73.3, $east->share($sales->totalInclTax));
    }

    #[Test]
    public function employees_without_an_organization_are_shown_separately(): void
    {
        $stores = $this->tree();

        $assigned = Employee::factory()->create(['organization_id' => $stores['tokyo']->id]);
        $unassigned = Employee::factory()->create(['name' => '所属なし 太郎', 'organization_id' => null]);

        $this->wonDeal($assigned, 100000);
        $this->wonDeal($unassigned, 50000);

        $sales = OrganizationSales::build();

        $last = $sales->regions[count($sales->regions) - 1];
        $this->assertSame('未所属', $last->name);
        $this->assertSame(50000, $last->amountInclTax);
        $this->assertSame('所属なし 太郎', $last->children[0]->name);

        // 未所属も合計には含める
        $this->assertSame(150000, $sales->totalInclTax);
    }

    #[Test]
    public function the_aggregation_is_two_queries_whatever_the_size(): void
    {
        $stores = $this->tree();

        foreach ($stores as $store) {
            $employee = Employee::factory()->create(['organization_id' => $store->id]);
            $this->wonDeal($employee, 100000);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        OrganizationSales::build();
        $baseline = count(DB::getQueryLog());

        // 組織・担当者・商談を増やす
        $extra = $this->organization('九州エリア', OrganizationType::Area, Organization::query()->ofType(OrganizationType::Region)->firstOrFail());
        $fukuoka = $this->organization('福岡支店', OrganizationType::Store, $extra);

        foreach (range(1, 5) as $i) {
            $employee = Employee::factory()->create(['organization_id' => $fukuoka->id]);
            $this->wonDeal($employee, 10000 * $i);
        }

        DB::flushQueryLog();
        OrganizationSales::build();
        $scaled = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $baseline, '担当者ぶんの集計 1 本と、組織の一覧 1 本だけ。');
        $this->assertSame($baseline, $scaled, '組織や担当者が増えてもクエリ本数は変わらない。');
    }

    #[Test]
    public function the_dashboard_shows_the_drill_down(): void
    {
        $this->actingAsStaff();
        $stores = $this->tree();

        $tokyo = Employee::factory()->create(['name' => '東京 太郎', 'organization_id' => $stores['tokyo']->id]);
        $this->wonDeal($tokyo, 800000);

        $response = $this->get(route('dashboard'))->assertOk();

        $response->assertSee('組織別の売上')
            ->assertSee('東日本地域')
            ->assertSee('首都圏エリア')
            ->assertSee('東京本店')
            ->assertSee('東京 太郎')
            ->assertSee('800,000');

        // 開閉できる(既定は地域だけ見えている)
        $html = $response->getContent();
        $this->assertStringContainsString('x-on:click="toggle(', $html);
        $this->assertStringContainsString('aria-expanded', $html);

        // 既存の担当者別グラフは残っている
        $response->assertSee('担当者別の売上（受注・税込）');
    }

    #[Test]
    public function the_totals_match_the_deal_table(): void
    {
        $stores = $this->tree();

        foreach ([120000, 340000, 560000] as $index => $amount) {
            $employee = Employee::factory()->create(['organization_id' => array_values($stores)[$index]->id]);
            $this->wonDeal($employee, $amount);
        }

        $sales = OrganizationSales::build();

        // 商談テーブルの受注合計と一致する(集計ロジックには手を入れていない)
        $this->assertSame(
            (int) Deal::query()->where('status', DealStatus::Won->value)->sum('amount_total'),
            $sales->totalInclTax,
        );
    }
}
