<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialCode;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商品分類マスタ。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
class ProductCategory extends BaseModel
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    use HasSequentialCode;

    protected $fillable = ['name', 'is_active'];

    public static function codePrefix(): string
    {
        return 'CAT';
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
