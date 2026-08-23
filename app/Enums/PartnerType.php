<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * 取引先区分。
 *
 * 受発注システムでは「得意先 = 売上先」「仕入先 = 仕入元」として参照する想定。
 */
enum PartnerType: string
{
    use HasOptions;

    /** 得意先(販売先) */
    case Customer = 'customer';

    /** 仕入先(購買先) */
    case Supplier = 'supplier';

    /** 得意先 兼 仕入先 */
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Customer => '得意先',
            self::Supplier => '仕入先',
            self::Both => '両方',
        };
    }

    /**
     * 得意先として扱えるか。
     */
    public function isCustomer(): bool
    {
        return $this === self::Customer || $this === self::Both;
    }

    /**
     * 仕入先として扱えるか。
     */
    public function isSupplier(): bool
    {
        return $this === self::Supplier || $this === self::Both;
    }
}
