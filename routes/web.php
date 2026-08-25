<?php

use App\Enums\PermissionName;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Crm\CustomerContactController;
use App\Http\Controllers\Crm\CustomerController;
use App\Http\Controllers\Crm\DealActivityController;
use App\Http\Controllers\Crm\DealController;
use App\Http\Controllers\Crm\DealStatusController;
use App\Http\Controllers\Crm\OptionsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Masters\DepartmentController;
use App\Http\Controllers\Masters\EmployeeController;
use App\Http\Controllers\Masters\MasterHubController;
use App\Http\Controllers\Masters\OrganizationController;
use App\Http\Controllers\Masters\PartnerController;
use App\Http\Controllers\Masters\PositionController;
use App\Http\Controllers\Masters\ProductCategoryController;
use App\Http\Controllers\Masters\ProductController;
use App\Http\Controllers\Masters\TaxRateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SavedViewController;
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

    // 一覧の保存ビュー(マイビュー)。自分のぶんだけ作れて、消せる
    Route::post('/saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
    Route::delete('/saved-views/{savedView}', [SavedViewController::class, 'destroy'])
        ->whereNumber('savedView')
        ->name('saved-views.destroy');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:'.PermissionName::ActivityLogView->value)
        ->name('activity-logs.index');

    // --- CRM: 顧客(会社 + 担当者) ----------------------------------------
    // 参照系は master.view、更新系は master.manage(基盤の権限をそのまま使う)
    Route::middleware('permission:'.PermissionName::MasterView->value)->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/{id}', [CustomerController::class, 'show'])
            ->whereNumber('id')
            ->name('customers.show');

        // コンボボックス(非同期モード)の候補。候補が多いときだけ画面から呼ばれる
        Route::get('options/customers', [OptionsController::class, 'customers'])->name('options.customers');
        Route::get('options/employees', [OptionsController::class, 'employees'])->name('options.employees');
        Route::get('options/products', [OptionsController::class, 'products'])->name('options.products');

        Route::get('deals', [DealController::class, 'index'])->name('deals.index');
        Route::get('deals/export', [DealController::class, 'export'])->name('deals.export');
        Route::get('deals/{id}', [DealController::class, 'show'])->whereNumber('id')->name('deals.show');
    });

    Route::middleware('permission:'.PermissionName::MasterManage->value)->group(function () {
        Route::delete('customers/{id}', [CustomerController::class, 'destroy'])->whereNumber('id')->name('customers.destroy');
        Route::post('customers/{id}/restore', [CustomerController::class, 'restore'])->whereNumber('id')->name('customers.restore');

        // 商談と明細
        Route::get('deals/create', [DealController::class, 'create'])->name('deals.create');
        Route::post('deals', [DealController::class, 'store'])->name('deals.store');
        Route::get('deals/{id}/edit', [DealController::class, 'edit'])->whereNumber('id')->name('deals.edit');
        Route::put('deals/{id}', [DealController::class, 'update'])->whereNumber('id')->name('deals.update');
        Route::delete('deals/{id}', [DealController::class, 'destroy'])->whereNumber('id')->name('deals.destroy');
        Route::post('deals/{id}/restore', [DealController::class, 'restore'])->whereNumber('id')->name('deals.restore');

        // カンバンでカードを動かしたときの、ステータスだけの更新
        Route::patch('deals/{id}/status', [DealStatusController::class, 'update'])
            ->whereNumber('id')
            ->name('deals.status.update');

        // 活動履歴は商談詳細の中だけで追加する
        Route::post('deals/{id}/activities', [DealActivityController::class, 'store'])
            ->whereNumber('id')
            ->name('deals.activities.store');

        // 担当者は顧客詳細のタブ内だけで扱う(独立画面は持たない)
        Route::post('customers/{id}/contacts', [CustomerContactController::class, 'store'])
            ->whereNumber('id')
            ->name('customers.contacts.store');
        Route::put('customers/{id}/contacts/{contact}', [CustomerContactController::class, 'update'])
            ->whereNumber(['id', 'contact'])
            ->name('customers.contacts.update');
    });

    // --- 共通マスタ -------------------------------------------------------
    // 一覧 / CSV は master.view、登録・編集・削除・復元は master.manage が必要
    Route::prefix('masters')->name('masters.')->group(function () {
        // 各マスタへの入口(ハブ)
        Route::get('/', [MasterHubController::class, 'index'])
            ->middleware('permission:'.PermissionName::MasterView->value)
            ->name('index');

        MasterRoutes::register('organizations', OrganizationController::class, 'organizations');
        MasterRoutes::register('employees', EmployeeController::class, 'employees');
        MasterRoutes::register('partners', PartnerController::class, 'partners');
        MasterRoutes::register('products', ProductController::class, 'products');

        // サブマスタ
        MasterRoutes::register('departments', DepartmentController::class, 'departments');
        MasterRoutes::register('positions', PositionController::class, 'positions');
        MasterRoutes::register('product-categories', ProductCategoryController::class, 'product-categories');

        // CRM 固有のマスタ
        MasterRoutes::register('tax-rates', TaxRateController::class, 'tax-rates');
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
