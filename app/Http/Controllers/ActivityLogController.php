<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\View\View;

/**
 * 操作ログの閲覧(参照のみ)。
 *
 * 権限 activity_log.view を持つロール(既定では admin)のみアクセスできる。
 */
class ActivityLogController extends Controller
{
    public function index(): View
    {
        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->newestFirst()
            ->paginate(25);

        return view('activity-logs.index', [
            'logs' => $logs,
        ]);
    }
}
