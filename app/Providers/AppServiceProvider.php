<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\BaseModel;
use App\Support\Crm\CrmMasterCatalog;
use App\Support\Crm\CrmNavigationMenu;
use App\Support\Masters\MasterCatalog;
use App\Support\Navigation\NavigationMenu;
use App\Support\Ui\Contracts\HolidayProvider;
use App\Support\Ui\NullHolidayProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // カレンダーの「特別な日(祝日など)」。既定は何も返さない実装。
        // 祝日をハイライトしたくなったら、ここを差し替える。
        $this->app->bind(HolidayProvider::class, NullHolidayProvider::class);

        // 左サイドナビとマスタ管理ハブの中身を CRM 用に差し替える
        $this->app->bind(NavigationMenu::class, CrmNavigationMenu::class);
        $this->app->bind(MasterCatalog::class, CrmMasterCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerBlueprintMacros();
        $this->configurePagination();
        $this->configureModels();
        $this->registerAuthenticationLogging();
    }

    /**
     * 業務テーブル共通のカラムを、マイグレーションから 1 行で追加できるようにする。
     *
     *   $table->auditColumns();
     *     → created_by / updated_by / created_at / updated_at / deleted_at
     *
     * @see BaseModel
     */
    protected function registerBlueprintMacros(): void
    {
        Blueprint::macro('auditColumns', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamps();
            $this->softDeletes();
        });

        // マスタテーブル共通。auditColumns に有効フラグを加えたもの。
        //   $table->masterColumns();
        //     → is_active / created_by / updated_by / created_at / updated_at / deleted_at
        Blueprint::macro('masterColumns', function (): void {
            /** @var Blueprint $this */
            $this->boolean('is_active')->default(true)->index();
            $this->auditColumns();
        });
    }

    /**
     * ページネーションの見た目を共通部品に差し替える。
     */
    protected function configurePagination(): void
    {
        Paginator::defaultView('pagination.app');
        Paginator::defaultSimpleView('pagination.simple');
    }

    protected function configureModels(): void
    {
        // fillable に無い属性を黙って捨てず、開発中に例外で気付けるようにする
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    /**
     * ログイン / ログアウトを操作ログに残す。
     */
    protected function registerAuthenticationLogging(): void
    {
        if (! config('activity_log.log_authentication', true)) {
            return;
        }

        Event::listen(function (Login $event): void {
            $this->recordAuthenticationActivity('logged_in', $event->user);
        });

        Event::listen(function (Logout $event): void {
            // ログアウト済みのセッションではユーザーが null になり得る
            $this->recordAuthenticationActivity('logged_out', $event->user);
        });
    }

    protected function recordAuthenticationActivity(string $action, ?Authenticatable $user): void
    {
        if ($user === null) {
            return;
        }

        $id = $user->getAuthIdentifier();
        $name = $user instanceof Model ? $user->getAttribute('name') : null;

        ActivityLog::record(
            action: $action,
            subject: $user instanceof Model ? $user : null,
            subjectLabel: is_string($name) ? $name : null,
            userId: is_int($id) ? $id : null,
        );
    }
}
