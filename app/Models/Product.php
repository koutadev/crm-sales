<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialCode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商品マスタ。
 *
 * 受発注の明細、EC の商品はこのテーブルを親として参照する想定。
 *
 * 単価は税込(内税統一)。税率は「これから使う標準税率」を持つだけで、金額の確定は行わない。
 * 商談明細は明細作成時点の単価・税率(%)をコピー保持するため、
 * ここを変更しても確定済みの金額には影響しない。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $product_category_id
 * @property int $unit_price
 * @property string|null $unit
 * @property int|null $tax_rate_id
 * @property-read ProductCategory|null $category
 * @property-read TaxRate|null $taxRate
 */
class Product extends BaseModel
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasSequentialCode;

    protected $fillable = [
        'name',
        'product_category_id',
        'unit_price',
        'unit',
        'tax_rate_id',
        'is_active',
    ];

    public static function codePrefix(): string
    {
        return 'PRD';
    }

    protected static function booted(): void
    {
        // 税率が未選択のまま保存された場合は既定の標準税率を割り当てる。
        // 明細の金額計算が必ず税率を引けるよう、NULL のまま保存させない。
        static::saving(function (self $product): void {
            if ($product->tax_rate_id === null) {
                $product->tax_rate_id = TaxRate::standard()?->id;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // 内税統一のため、単価は「税込・整数(最小通貨単位)」で扱う
        return [
            'unit_price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * 標準税率。商談明細に商品を追加したときの初期値として使う。
     *
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * この商品を使っている商談明細(CRM)。
     *
     * @return HasMany<DealItem, $this>
     */
    public function dealItems(): HasMany
    {
        return $this->hasMany(DealItem::class);
    }

    /**
     * 選択中の税率(%)。未設定(税率マスタが削除済み等)の場合は null。
     */
    public function taxRatePercent(): ?int
    {
        return $this->taxRate?->rate_percent;
    }

    public function activityLogLabel(): ?string
    {
        return $this->code.' '.$this->name;
    }
}
