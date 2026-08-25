<?php

namespace Tests\Feature;

use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 顧客・営業担当・商品のコンボボックス化(3-C)の検証。
 *
 * 一覧の絞り込みと商談フォームの両方で、入力して候補を絞れること。
 * 候補が多いマスタでは、全件を画面に埋め込まずサーバへ問い合わせる形に切り替わる。
 */
class DealComboboxTest extends TestCase
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

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function the_list_filters_customers_and_employees_with_a_combobox(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $response = $this->get(route('deals.index', ['reset' => 1]))->assertOk();
        $html = $response->getContent();

        // 顧客・営業担当はコンボボックス、ステータスは今までどおりセレクト
        $this->assertStringNotContainsString('<select id="dt-partner_id"', $html);
        $this->assertStringNotContainsString('<select id="dt-employee_id"', $html);
        $this->assertStringContainsString('<select id="dt-status"', $html);
        $this->assertStringContainsString('<input type="hidden" name="partner_id"', $html);
        $this->assertStringContainsString('role="combobox"', $html);

        // 候補が少ないので静的モード(候補を埋め込む)。かな検索用のキーも一緒に渡す
        $this->assertStringContainsString('アオイ商事株式会社', $html);
        $this->assertStringContainsString('あおい商事株式会社', $html);
        $this->assertStringNotContainsString('data-source=', $html);
    }

    #[Test]
    public function the_list_switches_to_the_async_mode_when_there_are_many_customers(): void
    {
        $this->actingAsRole(RoleName::Staff);

        Partner::factory()->count(101)->create(['partner_type' => PartnerType::Customer]);

        $html = $this->get(route('deals.index', ['reset' => 1]))->assertOk()->getContent();

        // 全件を埋め込む代わりに、入力のたびにサーバへ問い合わせる
        $this->assertStringContainsString('data-source="'.route('options.customers').'"', $html);
        $this->assertStringNotContainsString('アオイ商事株式会社', $html);
    }

    #[Test]
    public function the_async_mode_still_shows_the_name_of_the_selected_customer(): void
    {
        $this->actingAsRole(RoleName::Staff);

        Partner::factory()->count(101)->create(['partner_type' => PartnerType::Customer]);

        $html = $this->get(route('deals.index', ['partner_id' => $this->customer->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('アオイ商事株式会社', $html, '選択中の顧客名は候補がなくても出す。');
    }

    #[Test]
    public function filtering_by_a_customer_still_works_and_is_remembered(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $other = Partner::factory()->create(['name' => 'イロハ物産', 'partner_type' => PartnerType::Customer]);

        Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'title' => 'アオイ商事の案件',
            'amount_total' => 330000,
        ]);
        Deal::factory()->create([
            'partner_id' => $other->id,
            'employee_id' => $this->employee->id,
            'title' => 'イロハ物産の案件',
            'amount_total' => 220000,
        ]);

        $this->get(route('deals.index', ['partner_id' => $this->customer->id]))
            ->assertOk()
            ->assertSee('アオイ商事の案件')
            ->assertDontSee('イロハ物産の案件')
            ->assertSee('330,000');

        // 他の絞り込みと同じように前回の条件が残る
        $this->get(route('customers.index', ['reset' => 1]))->assertOk();

        $this->get(route('deals.index'))
            ->assertOk()
            ->assertSee('アオイ商事の案件')
            ->assertDontSee('イロハ物産の案件');
    }

    #[Test]
    public function an_invalid_customer_id_does_not_break_the_list(): void
    {
        $this->actingAsRole(RoleName::Staff);

        Partner::factory()->count(101)->create(['partner_type' => PartnerType::Customer]);

        // 非同期モードでは選択中のラベルを引くため、値をそのまま使う場面がある
        $this->get(route('deals.index', ['partner_id' => 'abc']))->assertOk();
    }

    #[Test]
    public function the_deal_form_uses_comboboxes(): void
    {
        $this->actingAsRole(RoleName::Admin);

        Product::factory()->create(['name' => 'コーポレートサイト制作']);
        PartnerContact::factory()->create([
            'partner_id' => $this->customer->id,
            'name' => '青井 一郎',
        ]);

        $html = $this->get(route('deals.create'))->assertOk()->getContent();

        // 顧客・営業担当・商品は候補を埋め込んだコンボボックス
        $this->assertStringContainsString('<input type="hidden" name="partner_id"', $html);
        $this->assertStringContainsString('<input type="hidden" name="employee_id"', $html);
        $this->assertStringContainsString('x-model="partnerId"', $html);
        $this->assertStringContainsString('こーぽれーとさいと', $html, '商品もかなで探せる。');

        // 先方担当は顧客に連動して候補が入れ替わる
        $this->assertStringContainsString('x-effect="setOptions(contactsForPartner())"', $html);
        $this->assertStringContainsString('x-model="contactId"', $html);

        // 明細行は行ごとに独立した部品として動く
        $this->assertStringContainsString(':name="`items[${index}][product_id]`"', $html);
        $this->assertStringContainsString('x-model="row.product_id"', $html);
        $this->assertStringContainsString('applyProduct(index, $event.detail.value)', $html);
    }

    #[Test]
    public function the_edit_form_shows_the_current_selections(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $deal = Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->get(route('deals.edit', $deal->id))
            ->assertOk()
            ->assertSee('アオイ商事株式会社')
            ->assertSee('営業 太郎');
    }

    #[Test]
    public function the_options_endpoint_searches_by_name_and_code(): void
    {
        $this->actingAsRole(RoleName::Staff);

        Partner::factory()->create(['name' => 'イロハ物産', 'partner_type' => PartnerType::Customer]);

        $all = $this->getJson(route('options.customers'))->assertOk()->json();
        $this->assertCount(2, $all);
        $this->assertSame(['value', 'label'], array_keys($all[0]));

        $this->getJson(route('options.customers', ['q' => 'アオイ']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['value' => (string) $this->customer->id, 'label' => 'アオイ商事株式会社']);

        // コードでも引ける
        $this->getJson(route('options.customers', ['q' => $this->customer->code]))
            ->assertOk()
            ->assertJsonCount(1);

        // 仕入先は顧客の候補に出てこない
        Partner::factory()->create(['name' => 'ウエノ資材', 'partner_type' => PartnerType::Supplier]);
        $this->getJson(route('options.customers', ['q' => 'ウエノ']))->assertOk()->assertJsonCount(0);
    }

    #[Test]
    public function the_options_endpoints_cover_employees_and_products(): void
    {
        $this->actingAsRole(RoleName::Staff);

        Product::factory()->create(['name' => 'コーポレートサイト制作']);

        $this->getJson(route('options.employees', ['q' => '営業']))
            ->assertOk()
            ->assertJsonFragment(['label' => '営業 太郎']);

        $this->getJson(route('options.products', ['q' => 'コーポレート']))
            ->assertOk()
            ->assertJsonFragment(['label' => 'コーポレートサイト制作']);
    }

    #[Test]
    public function the_options_endpoint_needs_permission(): void
    {
        // 未ログインなら弾かれる(JSON なので 401)
        $this->getJson(route('options.customers'))->assertUnauthorized();

        // 参照権限があれば読める(一覧と同じ権限)
        $this->actingAsRole(RoleName::Viewer);
        $this->getJson(route('options.customers'))->assertOk();
    }

    #[Test]
    public function the_list_does_not_load_more_options_than_needed(): void
    {
        $this->actingAsRole(RoleName::Staff);

        Partner::factory()->count(101)->create(['partner_type' => PartnerType::Customer]);
        Deal::factory()->count(3)->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
        ]);

        // 非同期モードでも、件数を数えるためだけのクエリは足さない
        $this->get(route('deals.index', ['reset' => 1]))->assertOk();

        $queries = $this->countQueries(fn () => $this->get(route('deals.index'))->assertOk());

        $this->assertSame(8, $queries, '商談一覧のクエリ本数は 8 本のまま。');
    }

    #[Test]
    public function the_static_mode_is_used_up_to_the_threshold(): void
    {
        $this->actingAsRole(RoleName::Staff);

        // ちょうど上限(100 件)までは候補を埋め込む
        Partner::factory()->count(99)->create(['partner_type' => PartnerType::Customer]);

        $html = $this->get(route('deals.index', ['reset' => 1]))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-source=', $html);
        $this->assertSame(100, Partner::query()->customers()->count());
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
