<?php

namespace App\Support\Theme;

use Illuminate\Support\HtmlString;

/**
 * テーマ（サービス名・ロゴ・配色）へのアクセス。
 *
 * 配色は config/theme.php の値を CSS カスタムプロパティとして <head> に注入する。
 * Tailwind のユーティリティ（bg-primary など）は var(--color-primary) を参照しているため、
 * 変数を上書きするだけで再ビルドなしに配色が切り替わる。
 *
 * @see config/theme.php
 */
class Theme
{
    /**
     * config に書ける色の形式。
     * <style> に直接埋め込むため、想定外の文字列は既定色にフォールバックさせる。
     */
    private const COLOR_PATTERN = '/^(#[0-9a-fA-F]{3,8}|(rgb|rgba|hsl|hsla|oklch|oklab|lab|lch|color)\([0-9a-zA-Z%.,\/\s+-]+\)|[a-zA-Z]+)$/';

    private const FALLBACK_PRIMARY = '#4f46e5';

    private const FALLBACK_ACCENT = '#0891b2';

    public static function name(): string
    {
        $name = config('theme.name');

        return is_string($name) && $name !== '' ? $name : 'Business Template';
    }

    public static function tagline(): string
    {
        $tagline = config('theme.tagline');

        return is_string($tagline) ? $tagline : '';
    }

    /**
     * ロゴ画像の URL。未設定なら null（既定のマークを表示する）。
     */
    public static function logoUrl(): ?string
    {
        $logo = config('theme.logo');

        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }

        // 外部URLならそのまま、それ以外は public/ 配下として扱う
        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        return asset(ltrim($logo, '/'));
    }

    /**
     * ロゴ未設定時に使う頭文字。
     */
    public static function initial(): string
    {
        return mb_substr(self::name(), 0, 1);
    }

    public static function primary(): string
    {
        return self::sanitizeColor(config('theme.colors.primary'), self::FALLBACK_PRIMARY);
    }

    public static function accent(): string
    {
        return self::sanitizeColor(config('theme.colors.accent'), self::FALLBACK_ACCENT);
    }

    /**
     * <head> に差し込む CSS カスタムプロパティ。
     */
    public static function cssVariables(): HtmlString
    {
        $css = sprintf(
            ':root{--color-primary:%s;--color-accent:%s;}',
            self::primary(),
            self::accent(),
        );

        return new HtmlString($css);
    }

    /**
     * グラフに使う色。config の null 位置に primary / accent が入る。
     *
     * @return list<string>
     */
    public static function chartPalette(): array
    {
        /** @var list<string|null> $palette */
        $palette = config('theme.chart_palette', []);

        $brand = [self::primary(), self::accent()];
        $colors = [];

        foreach ($palette as $color) {
            if ($color === null) {
                $next = array_shift($brand);

                if ($next !== null) {
                    $colors[] = $next;
                }

                continue;
            }

            $colors[] = self::sanitizeColor($color, '#64748b');
        }

        return $colors === [] ? [self::primary(), self::accent()] : $colors;
    }

    /**
     * CSS に埋め込んで安全な色表現だけを通す。
     */
    private static function sanitizeColor(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $value = trim($value);

        return preg_match(self::COLOR_PATTERN, $value) === 1 ? $value : $fallback;
    }
}
