<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialCode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品マスタ。
 *
 * 受発注の明細、EC の商品はこのテーブルを親として参照する想定。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $product_category_id
 * @property string $unit_price
 * @property string|null $unit
 * @property-read ProductCategory|null $category
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
        'is_active',
    ];

    public static function codePrefix(): string
    {
        return 'PRD';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // 通貨は誤差を避けるため decimal のまま扱う(float にしない)
        return [
            'unit_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function activityLogLabel(): ?string
    {
        return $this->code.' '.$this->name;
    }
}
