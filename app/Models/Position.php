<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialCode;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 役職マスタ。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
class Position extends BaseModel
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    use HasSequentialCode;

    protected $fillable = ['name', 'is_active'];

    public static function codePrefix(): string
    {
        return 'POS';
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
