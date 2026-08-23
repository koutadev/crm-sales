<?php

use App\Enums\PermissionName;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Masters\DepartmentController;
use App\Http\Controllers\Masters\EmployeeController;
use App\Http\Controllers\Masters\PartnerController;
use App\Http\Controllers\Masters\PositionController;
use App\Http\Controllers\Masters\ProductCategoryController;
use App\Http\Controllers\Masters\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Support\Routing\MasterRoutes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // 環境の疎通確認用(STEP 1 から継続)
    try {
        $pdo = DB::connection()->getPdo();

        $database = [
            'connected' => true,
            'message' => sprintf(
                '%s / %s',
                DB::connection()->getDatabaseName(),
                $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            ),
        ];
    } catch (Throwable $e) {
        $database = [
            'connected' => false,
            'message' => $e->getMessage(),
        ];
    }

    return view('welcome', [
        'database' => $database,
        'status' => [
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Database' => config('database.default'),
        ],
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:'.PermissionName::DashboardView->value)
        ->name('dashboard');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:'.PermissionName::ActivityLogView->value)
        ->name('activity-logs.index');

    // --- 共通マスタ -------------------------------------------------------
    // 一覧 / CSV は master.view、登録・編集・削除・復元は master.manage が必要
    Route::prefix('masters')->name('masters.')->group(function () {
        MasterRoutes::register('employees', EmployeeController::class, 'employees');
        MasterRoutes::register('partners', PartnerController::class, 'partners');
        MasterRoutes::register('products', ProductController::class, 'products');

        // サブマスタ
        MasterRoutes::register('departments', DepartmentController::class, 'departments');
        MasterRoutes::register('positions', PositionController::class, 'positions');
        MasterRoutes::register('product-categories', ProductCategoryController::class, 'product-categories');
    });

    // --- ユーザー管理(ロールの付け替え) ----------------------------------
    Route::middleware('permission:'.PermissionName::UserManage->value)
        ->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
        });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
