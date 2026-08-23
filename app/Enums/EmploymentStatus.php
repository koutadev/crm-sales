<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * 社員の在籍状態。
 *
 * 「在籍しているか」と「マスタとして有効か(is_active)」は別の概念。
 * 退職者でも過去伝票から参照されるため、レコード自体は残す。
 */
enum EmploymentStatus: string
{
    use HasOptions;

    /** 在籍中 */
    case Active = 'active';

    /** 休職中 */
    case Leave = 'leave';

    /** 退職済み */
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => '在籍',
            self::Leave => '休職',
            self::Retired => '退職',
        };
    }

    /**
     * 一覧のバッジ色(Tailwind のクラス)。
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
            self::Leave => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
            self::Retired => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    }
}
