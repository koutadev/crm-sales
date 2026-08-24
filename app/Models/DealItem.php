<?php

namespace App\Models;

use Database\Factories\DealItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商談明細。
 *
 * 明細を作った時点の税込単価と税率(%)をコピー保持するため、
 * 商品マスタ・税率マスタが後から変わっても確定済みの金額は動かない。
 *
 * 金額は税込(amount_incl_tax)が正で、消費税額・税抜金額はそこからの逆算値。
 * 逆算と商談合計の再計算は STEP 4 で実装する(このモデルでは値を保持するだけ)。
 *
 * @property int $id
 * @property int $deal_id
 * @property int $product_id
 * @property int $quantity
 * @property int $unit_price
 * @property int $tax_rate_id
 * @property int $tax_rate_percent
 * @property int $amount_incl_tax
 * @property int $tax_amount
 * @property int $amount_excl_tax
 * @property-read Deal|null $deal
 * @property-read Product|null $product
 * @property-read TaxRate|null $taxRate
 */
class DealItem extends BaseModel
{
    /** @use HasFactory<DealItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // 明細が変われば商談の税込合計も変わる。
        // どの経路(画面・シーダー・バッチ)から更新しても金額が合うようにモデル側で拾う。
        static::saved(static fn (self $item) => $item->refreshDealAmounts());
        static::deleted(static fn (self $item) => $item->refreshDealAmounts());
        static::restored(static fn (self $item) => $item->refreshDealAmounts());
    }

    protected $fillable = [
        'deal_id',
        'product_id',
        'quantity',
        'unit_price',
        'tax_rate_id',
        'tax_rate_percent',
        'amount_incl_tax',
        'tax_amount',
        'amount_excl_tax',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // 金額はすべて税込・整数(最小通貨単位)で保持する
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'tax_rate_percent' => 'integer',
            'amount_incl_tax' => 'integer',
            'tax_amount' => 'integer',
            'amount_excl_tax' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 確定時点の税率。金額の正は tax_rate_percent(スナップショット)で、
     * この関連は「どの世代から引いたか」を追跡するために持つ。
     *
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * 親の商談の金額を計算し直す。
     */
    private function refreshDealAmounts(): void
    {
        if (Deal::amountRecalculationSuspended()) {
            return;
        }

        $this->deal?->recalculateAmounts();
    }

    public function activityLogLabel(): ?string
    {
        return $this->product?->name;
    }
}
