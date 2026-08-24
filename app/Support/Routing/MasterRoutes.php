<?php

namespace App\Support\Routing;

use App\Enums\PermissionName;
use Illuminate\Support\Facades\Route;

/**
 * マスタ画面のルートをまとめて登録する。
 *
 *   MasterRoutes::register('employees', EmployeeController::class, 'employees');
 *
 * 参照系(一覧・CSV)は master.view、更新系(登録・編集・削除・復元)は master.manage が必要。
 * マスタ単位に権限を分けたくなった場合は、ここで渡す権限名を引数にすればよい。
 */
class MasterRoutes
{
    /**
     * @param  class-string  $controller
     */
    public static function register(string $uri, string $controller, string $name): void
    {
        Route::middleware('permission:'.PermissionName::MasterView->value)->group(function () use ($uri, $controller, $name): void {
            Route::get($uri, [$controller, 'index'])->name($name.'.index');
            Route::get($uri.'/export', [$controller, 'export'])->name($name.'.export');

            // 一覧の行クリックで開くモーダルの中身(HTML の断片)
            Route::get($uri.'/{id}/detail', [$controller, 'detail'])
                ->whereNumber('id')
                ->name($name.'.detail');
        });

        Route::middleware('permission:'.PermissionName::MasterManage->value)->group(function () use ($uri, $controller, $name): void {
            Route::get($uri.'/create', [$controller, 'create'])->name($name.'.create');
            Route::post($uri, [$controller, 'store'])->name($name.'.store');
            Route::get($uri.'/{id}/edit', [$controller, 'edit'])->name($name.'.edit');
            Route::put($uri.'/{id}', [$controller, 'update'])->name($name.'.update');
            Route::delete($uri.'/{id}', [$controller, 'destroy'])->name($name.'.destroy');
            Route::post($uri.'/{id}/restore', [$controller, 'restore'])->name($name.'.restore');
        });
    }
}
