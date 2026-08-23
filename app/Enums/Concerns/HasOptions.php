<?php

namespace App\Enums\Concerns;

/**
 * 値と日本語ラベルを持つ enum に、選択肢配列を作る機能を追加する。
 *
 * 使う側の enum は string バックドで label(): string を実装すること。
 */
trait HasOptions
{
    /**
     * セレクトボックス用の [値 => ラベル] を返す。
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
