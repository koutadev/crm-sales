<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * 売上目標の対象粒度。
 *
 * 全社は 1 本、地域・エリア・店舗は組織、担当者は社員を指す。
 * 「どのテーブルの ID か」をこの enum が決める。
 */
enum TargetScope: string
{
    use HasOptions;

    /** 全社（対象 ID を持たない） */
    case Company = 'company';

    case Region = 'region';

    case Area = 'area';

    case Store = 'store';

    /** 担当者（社員） */
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Company => '全社',
            self::Region => '地域',
            self::Area => 'エリア',
            self::Store => '店舗',
            self::Employee => '担当者',
        };
    }

    /**
     * 対象 ID を持つか（全社だけ持たない）。
     */
    public function needsTarget(): bool
    {
        return $this !== self::Company;
    }

    /**
     * 組織を指す粒度なら、その組織種別。
     */
    public function organizationType(): ?OrganizationType
    {
        return match ($this) {
            self::Region => OrganizationType::Region,
            self::Area => OrganizationType::Area,
            self::Store => OrganizationType::Store,
            default => null,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Company => 'bg-primary-soft text-primary-soft-fg',
            self::Region => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
            self::Area => 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
            self::Store => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            self::Employee => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        };
    }
}
