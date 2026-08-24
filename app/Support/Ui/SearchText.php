<?php

namespace App\Support\Ui;

/**
 * 検索文字列の正規化。
 *
 * 「あおい」で「アオイ商事」を見つけられるように、
 * 入力と候補の両方を同じ形に揃えてから比較する。
 *
 *   - 半角カタカナ → 全角カタカナ(濁点も 1 文字に結合)
 *   - 全角の英数字・記号・空白 → 半角
 *   - カタカナ → ひらがな
 *   - 英字は小文字に揃える
 *
 * クライアント側(resources/js/search-text.js)にも同じ規則を実装してある。
 * 非同期モードのエンドポイントでは、この正規化を通してから比較すること。
 */
class SearchText
{
    public static function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // K: 半角カナ → 全角カナ / V: 濁点の結合 / a: 全角英数 → 半角 / s: 全角空白 → 半角
        $normalized = mb_convert_kana($value, 'KVas');

        // c: 全角カタカナ → ひらがな
        $normalized = mb_convert_kana($normalized, 'c');

        return mb_strtolower(trim($normalized));
    }

    /**
     * 正規化したうえでの部分一致。
     */
    public static function matches(?string $haystack, ?string $needle): bool
    {
        $needle = self::normalize($needle);

        if ($needle === '') {
            return true;
        }

        return str_contains(self::normalize($haystack), $needle);
    }
}
