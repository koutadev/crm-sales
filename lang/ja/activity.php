<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 操作ログの操作名
    |--------------------------------------------------------------------------
    |
    | ActivityLog::record() に渡した action に対応する表示名。
    | 独自の操作を記録する場合はここに追記する。
    |
    | @see \App\Models\ActivityLog::actionLabel()
    |
    */

    'actions' => [
        'created' => '作成',
        'updated' => '更新',
        'deleted' => '削除',
        'restored' => '復元',
        'force_deleted' => '完全削除',
        'logged_in' => 'ログイン',
        'logged_out' => 'ログアウト',
    ],

];
