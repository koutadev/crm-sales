<?php

namespace App\Support\Routing;

use App\Enums\PermissionName;
use Illuminate\Support\Facades\Route;

/**
 * マスタ画面のルートをまとめて登録する。
 *
 *   MasterRoutes::register('employees', EmployeeController::class, 'employees');
 *
 * 既定では参照系(一覧・CSV)に master.view、更新系(登録・編集・削除・復元)に master.manage が必要。
 * マスタ単位に権限を分けたい場合は、必要な権限を引数で渡す。
 *
 *   // 管理者だけが扱えるマスタ
 *   MasterRoutes::register(
 *       'instructors', InstructorController::class, 'instructors',
 *       PermissionName::InstructorManage, PermissionName::InstructorManage,
 *   );
 */
class MasterRoutes
{
    /**
     * @param  class-string  $controller
     * @param  PermissionName|null  $viewPermission  一覧・CSV・詳細に必要な権限(既定: master.view)
     * @param  PermissionName|null  $managePermission  登録・編集・削除・復元に必要な権限(既定: master.manage)
     */
    public static function register(
        string $uri,
        string $controller,
        string $name,
        ?PermissionName $viewPermission = null,
        ?PermissionName $managePermission = null,
    ): void {
        $viewPermission ??= PermissionName::MasterView;
        $managePermission ??= PermissionName::MasterManage;

        Route::middleware('permission:'.$viewPermission->value)->group(function () use ($uri, $controller, $name): void {
            Route::get($uri, [$controller, 'index'])->name($name.'.index');
            Route::get($uri.'/export', [$controller, 'export'])->name($name.'.export');

            // 一覧の行クリックで開くモーダルの中身(HTML の断片)
            Route::get($uri.'/{id}/detail', [$controller, 'detail'])
                ->whereNumber('id')
                ->name($name.'.detail');
        });

        Route::middleware('permission:'.$managePermission->value)->group(function () use ($uri, $controller, $name): void {
            Route::get($uri.'/create', [$controller, 'create'])->name($name.'.create');
            Route::post($uri, [$controller, 'store'])->name($name.'.store');
            Route::get($uri.'/{id}/edit', [$controller, 'edit'])->name($name.'.edit');
            Route::put($uri.'/{id}', [$controller, 'update'])->name($name.'.update');
            Route::delete($uri.'/{id}', [$controller, 'destroy'])->name($name.'.destroy');
            Route::post($uri.'/{id}/restore', [$controller, 'restore'])->name($name.'.restore');
        });
    }
}
