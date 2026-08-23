<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * 活動履歴の種別。
 */
enum ActivityType: string
{
    use HasOptions;

    /** 電話 */
    case Phone = 'phone';

    /** 訪問 */
    case Visit = 'visit';

    /** メール */
    case Email = 'email';

    /** メモ(社内向けの記録) */
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Phone => '電話',
            self::Visit => '訪問',
            self::Email => 'メール',
            self::Note => 'メモ',
        };
    }

    /**
     * 一覧のバッジ色(Tailwind のクラス)。
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Phone => 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
            self::Visit => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
            self::Email => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
            self::Note => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    }
}
