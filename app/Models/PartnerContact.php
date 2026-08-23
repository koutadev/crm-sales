<?php

namespace App\Models;

use Database\Factories\PartnerContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 取引先担当者(先方の窓口となる個人)。
 *
 * @property int $id
 * @property int $partner_id
 * @property string $name
 * @property string|null $department
 * @property string|null $position
 * @property string|null $email
 * @property string|null $phone
 * @property-read Partner|null $partner
 */
class PartnerContact extends BaseModel
{
    /** @use HasFactory<PartnerContactFactory> */
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'name',
        'department',
        'position',
        'email',
        'phone',
        'is_active',
    ];

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * この担当者が先方担当になっている商談。
     *
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activityLogLabel(): ?string
    {
        return $this->name;
    }
}
