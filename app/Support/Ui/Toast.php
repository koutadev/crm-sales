<?php

namespace App\Support\Ui;

/**
 * トースト通知。
 *
 * リダイレクト時にセッションへ載せると、次の画面で自動的に表示される。
 *
 *   return redirect()->route('masters.employees.index')
 *       ->with(Toast::SESSION_KEY, Toast::success('社員を登録しました。'));
 *
 * 画面側から出したい場合は Alpine のイベントを投げる。
 *
 *   $dispatch('toast', { type: 'error', message: '保存に失敗しました' })
 */
class Toast
{
    /** セッションに載せるときのキー */
    public const SESSION_KEY = 'toast';

    /**
     * @return array{type: string, message: string}
     */
    public static function success(string $message): array
    {
        return self::make(Tone::Success, $message);
    }

    /**
     * @return array{type: string, message: string}
     */
    public static function error(string $message): array
    {
        return self::make(Tone::Danger, $message);
    }

    /**
     * @return array{type: string, message: string}
     */
    public static function info(string $message): array
    {
        return self::make(Tone::Info, $message);
    }

    /**
     * @return array{type: string, message: string}
     */
    public static function make(Tone $tone, string $message): array
    {
        return ['type' => $tone->value, 'message' => $message];
    }
}
