<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * 法人 / 個人の区分。
 */
enum EntityType: string
{
    use HasOptions;

    case Corporate = 'corporate';

    case Individual = 'individual';

    public function label(): string
    {
        return match ($this) {
            self::Corporate => '法人',
            self::Individual => '個人',
        };
    }
}
