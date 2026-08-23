<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\DealStatus;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CRM 固有テーブル(取引先担当者 / 商談 / 商談明細 / 活動履歴)の
 * データ構造・リレーション・採番の検証。
 *
 * 金額の自動計算は STEP 4 で実装するため、ここでは値を保持できることまでを見る。
 */
class CrmStructureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deal_codes_are_numbered_per_year(): void
    {
        $this->travelTo(Carbon::parse('2026-05-01'));

        $this->assertSame('DEAL-2026-0001', Deal::factory()->create()->code);
        $this->assertSame('DEAL-2026-0002', Deal::factory()->create()->code);

        // 年が変わったら連番は 1 に戻る(採番系列がキーごとに分かれている)
        $this->travelTo(Carbon::parse('2027-01-05'));

        $this->assertSame('DEAL-2027-0001', Deal::factory()->create()->code);

        $this->assertDatabaseHas('code_sequences', ['key' => 'deals:2026']);
        $this->assertDatabaseHas('code_sequences', ['key' => 'deals:2027']);
    }

    #[Test]
    public function a_deal_is_linked_to_its_partner_contact_employee_items_and_activities(): void
    {
        $partner = Partner::factory()->create();
        $contact = PartnerContact::factory()->create(['partner_id' => $partner->id]);
        $employee = Employee::factory()->create();

        $deal = Deal::factory()->create([
            'partner_id' => $partner->id,
            'partner_contact_id' => $contact->id,
            'employee_id' => $employee->id,
        ]);

        DealItem::factory()->count(2)->create(['deal_id' => $deal->id]);

        Activity::factory()->create([
            'partner_id' => $partner->id,
            'deal_id' => $deal->id,
            'employee_id' => $employee->id,
        ]);

        $loaded = Deal::query()
            ->with(['partner', 'partnerContact', 'employee', 'items', 'activities'])
            ->findOrFail($deal->id);

        $this->assertTrue($loaded->partner?->is($partner));
        $this->assertTrue($loaded->partnerContact?->is($contact));
        $this->assertTrue($loaded->employee?->is($employee));
        $this->assertCount(2, $loaded->items);
        $this->assertCount(1, $loaded->activities);

        // 逆方向
        $this->assertTrue($partner->contacts()->whereKey($contact->id)->exists());
        $this->assertSame(1, $partner->deals()->count());
        $this->assertSame(1, $partner->activities()->count());
        $this->assertSame(1, $employee->deals()->count());
        $this->assertSame(1, $employee->activities()->count());
        $this->assertSame(1, $contact->deals()->count());
    }

    #[Test]
    public function a_deal_item_is_linked_to_its_product_and_tax_rate(): void
    {
        $product = Product::factory()->create();
        $taxRate = TaxRate::factory()->create();

        $item = DealItem::factory()->create([
            'product_id' => $product->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $this->assertTrue($item->product?->is($product));
        $this->assertTrue($item->taxRate?->is($taxRate));
        $this->assertSame(1, $product->dealItems()->count());
        $this->assertSame(1, $taxRate->dealItems()->count());
    }

    #[Test]
    public function amounts_are_kept_as_tax_inclusive_integers(): void
    {
        // 金額は手で入れられる(自動計算は STEP 4)。税込 22,000 / 10% の内訳を保持する
        $item = DealItem::factory()->create([
            'quantity' => 2,
            'unit_price' => 11000,
            'tax_rate_percent' => 10,
            'amount_incl_tax' => 22000,
            'tax_amount' => 2000,
            'amount_excl_tax' => 20000,
        ]);

        $item->refresh();

        $this->assertSame(22000, $item->amount_incl_tax);
        $this->assertSame(2000, $item->tax_amount);
        $this->assertSame(20000, $item->amount_excl_tax);
        $this->assertSame(11000, $item->unit_price);

        $deal = $item->deal;
        $this->assertNotNull($deal);

        $deal->update(['amount_total' => 22000]);
        $this->assertSame(22000, $deal->refresh()->amount_total);
    }

    #[Test]
    public function a_deal_item_keeps_the_tax_rate_of_the_moment(): void
    {
        $taxRate = TaxRate::factory()->create(['rate_percent' => 10]);

        $item = DealItem::factory()->create([
            'tax_rate_id' => $taxRate->id,
            'tax_rate_percent' => 10,
        ]);

        // 税率マスタ側を書き換えても、明細のスナップショットは動かない
        $taxRate->update(['rate_percent' => 12]);

        $this->assertSame(10, $item->refresh()->tax_rate_percent);
        $this->assertSame(12, $item->taxRate?->refresh()->rate_percent);
    }

    #[Test]
    public function status_and_type_are_handled_as_enums(): void
    {
        $deal = Deal::factory()->won()->create();

        $this->assertSame(DealStatus::Won, $deal->refresh()->status);
        $this->assertTrue($deal->status->isWon());
        $this->assertTrue($deal->status->isClosed());
        $this->assertSame('受注', $deal->status->label());

        $this->assertSame(1, Deal::query()->won()->count());
        $this->assertSame(0, Deal::query()->open()->count());

        $activity = Activity::factory()->create(['type' => ActivityType::Visit]);

        $this->assertSame(ActivityType::Visit, $activity->refresh()->type);
        $this->assertSame('訪問', $activity->type->label());
    }

    #[Test]
    public function an_activity_can_be_recorded_without_a_deal(): void
    {
        $activity = Activity::factory()->create(['deal_id' => null]);

        $this->assertNull($activity->deal);
        $this->assertNotNull($activity->partner);
        $this->assertNotNull($activity->employee);
    }

    #[Test]
    public function crm_records_follow_the_common_model_conventions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $deal = Deal::factory()->create();

        // 作成者・更新者の自動記録
        $this->assertSame($user->id, $deal->created_by);
        $this->assertSame($user->id, $deal->updated_by);
        $this->assertTrue($deal->is_active);

        // 操作ログ
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'subject_type' => Deal::class,
            'subject_id' => $deal->id,
        ]);

        // 論理削除
        $deal->delete();
        $this->assertSoftDeleted($deal);
        $this->assertNotNull(Deal::withTrashed()->find($deal->id));
    }

    #[Test]
    public function contacts_are_removed_with_their_partner_only_on_a_hard_delete(): void
    {
        $partner = Partner::factory()->create();
        $contact = PartnerContact::factory()->create(['partner_id' => $partner->id]);

        // 通常運用は論理削除。担当者は残る
        $partner->delete();
        $this->assertSoftDeleted($partner);
        $this->assertNotSoftDeleted($contact->fresh());

        // 物理削除したときだけ、外部キーの cascade で担当者も消える
        $partner->forceDelete();
        $this->assertDatabaseMissing('partner_contacts', ['id' => $contact->id]);
    }
}
