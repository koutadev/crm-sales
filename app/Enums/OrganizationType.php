<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * 組織の種別。
 *
 * 地域 > エリア > 店舗 の 3 段だけを扱う（無限階層にはしない）。
 * 段数を固定しているので、階層の集計も少ないクエリで済む。
 */
enum OrganizationType: string
{
    use HasOptions;

    /** 地域（最上位。親を持たない） */
    case Region = 'region';

    /** エリア（親は地域） */
    case Area = 'area';

    /** 店舗（親はエリア。社員はここに所属する） */
    case Store = 'store';

    public function label(): string
    {
        return match ($this) {
            self::Region => '地域',
            self::Area => 'エリア',
            self::Store => '店舗',
        };
    }

    /**
     * 階層の深さ（地域 = 1）。
     */
    public function depth(): int
    {
        return match ($this) {
            self::Region => 1,
            self::Area => 2,
            self::Store => 3,
        };
    }

    /**
     * 親に選べる種別（地域は親を持たない）。
     */
    public function parentType(): ?self
    {
        return match ($this) {
            self::Region => null,
            self::Area => self::Region,
            self::Store => self::Area,
        };
    }

    /**
     * 子に持てる種別（店舗の下は作らない）。
     */
    public function childType(): ?self
    {
        return match ($this) {
            self::Region => self::Area,
            self::Area => self::Store,
            self::Store => null,
        };
    }

    /**
     * 一覧のバッジ色（Tailwind のクラス）。
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Region => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
            self::Area => 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
            self::Store => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    }

    /**
     * 社員が所属する種別（＝最下層）。
     */
    public static function assignable(): self
    {
        return self::Store;
    }
}
