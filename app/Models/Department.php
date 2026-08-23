<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialCode;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 部署マスタ。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
class Department extends BaseModel
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use HasSequentialCode;

    protected $fillable = ['name', 'is_active'];

    public static function codePrefix(): string
    {
        return 'DEP';
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
