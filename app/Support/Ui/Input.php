<?php

namespace App\Support\Ui;

/**
 * 入力欄の見た目。
 *
 * テキスト・数値・日付・セレクトで同じ枠線・フォーカス・エラー表示を使うため、
 * クラスの組み立てをここにまとめている。
 */
class Input
{
    public static function classes(Size $size, bool $hasError = false): string
    {
        return implode(' ', [
            'block w-full rounded-md shadow-sm',
            'focus:border-primary focus:ring-primary',
            'disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500',
            'dark:bg-gray-900 dark:text-gray-200 dark:disabled:bg-gray-800',
            $size->inputClasses(),
            $hasError
                ? 'border-rose-400 text-rose-900 focus:border-rose-500 focus:ring-rose-500 dark:border-rose-500 dark:text-rose-200'
                : 'border-gray-300 dark:border-gray-700',
        ]);
    }

    /**
     * チェックボックス・ラジオの共通クラス。
     */
    public static function checkableClasses(bool $rounded = true): string
    {
        return implode(' ', [
            $rounded ? 'rounded' : 'rounded-full',
            'border-gray-300 text-primary shadow-sm',
            'focus:ring-primary disabled:cursor-not-allowed disabled:opacity-50',
            'dark:border-gray-700 dark:bg-gray-900',
        ]);
    }
}
