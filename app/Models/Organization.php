<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Models\Concerns\HasSequentialCode;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 組織マスタ（地域 > エリア > 店舗）。
 *
 * 3 段に固定しているので、階層をたどる処理は再帰なしで書ける。
 * 社員は最下層（店舗）に所属し、売上などは担当者経由でここへ集まる。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property OrganizationType $type
 * @property string|null $prefecture
 * @property int|null $parent_id
 * @property-read Organization|null $parent
 * @property-read Collection<int, Organization> $children
 * @property-read Collection<int, Employee> $employees
 */
class Organization extends BaseModel
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use HasSequentialCode;

    protected $fillable = [
        'name',
        'type',
        'prefecture',
        'parent_id',
        'is_active',
    ];

    public static function codePrefix(): string
    {
        return 'ORG';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Organization, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * 種別で絞る。
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeOfType(Builder $query, OrganizationType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * 都道府県で絞る（店舗にだけ入っている）。
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeInPrefecture(Builder $query, string $prefecture): Builder
    {
        return $query->where('prefecture', $prefecture);
    }

    /**
     * 「地域 > エリア > 店舗」の表示。
     *
     * 親を eager load していない場合は、その場で読みに行くので注意
     * （一覧では with('parent.parent') を付けてある）。
     */
    public function path(string $separator = ' > '): string
    {
        $names = [];

        for ($node = $this; $node !== null; $node = $node->parent) {
            array_unshift($names, $node->name);
        }

        return implode($separator, $names);
    }

    /**
     * 自分より上位の組織（近い順）。
     *
     * @return list<Organization>
     */
    public function ancestors(): array
    {
        $ancestors = [];

        for ($node = $this->parent; $node !== null; $node = $node->parent) {
            $ancestors[] = $node;
        }

        return $ancestors;
    }
}
