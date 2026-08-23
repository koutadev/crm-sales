<?php

namespace App\Models;

use App\Enums\EntityType;
use App\Enums\PartnerType;
use App\Models\Concerns\HasSequentialCode;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 取引先マスタ。
 *
 * CRM の顧客、受発注の得意先 / 仕入先は、いずれこのテーブルを親として参照する想定。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property PartnerType $partner_type
 * @property EntityType $entity_type
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $postal_code
 * @property string|null $address
 */
class Partner extends BaseModel
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

    use HasSequentialCode;

    protected $fillable = [
        'name',
        'partner_type',
        'entity_type',
        'email',
        'phone',
        'postal_code',
        'address',
        'is_active',
    ];

    public static function codePrefix(): string
    {
        return 'PTR';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'partner_type' => PartnerType::class,
            'entity_type' => EntityType::class,
        ];
    }

    /**
     * 得意先として使える取引先(受発注・CRM から利用する想定)。
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('partner_type', [PartnerType::Customer->value, PartnerType::Both->value]);
    }

    /**
     * 仕入先として使える取引先。
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->whereIn('partner_type', [PartnerType::Supplier->value, PartnerType::Both->value]);
    }

    public function activityLogLabel(): ?string
    {
        return $this->code.' '.$this->name;
    }
}
