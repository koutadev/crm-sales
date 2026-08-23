<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Theme\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * テーマ差し替え口（config/theme.php）の検証。
 */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function changing_the_config_switches_the_service_name_and_colors(): void
    {
        config([
            'theme.name' => 'Acme 販売管理',
            'theme.colors.primary' => '#c026d3',
            'theme.colors.accent' => '#ea580c',
        ]);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('<title>Acme 販売管理</title>', false);
        $response->assertSee('--color-primary:#c026d3', false);
        $response->assertSee('--color-accent:#ea580c', false);
    }

    #[Test]
    public function the_login_screen_follows_the_theme(): void
    {
        config([
            'theme.name' => 'Acme 販売管理',
            'theme.tagline' => '株式会社Acme 社内システム',
        ]);

        $response = $this->get(route('login'));

        $response->assertSee('Acme 販売管理');
        $response->assertSee('株式会社Acme 社内システム');
    }

    #[Test]
    public function the_chart_palette_starts_with_the_theme_colors(): void
    {
        config([
            'theme.colors.primary' => '#c026d3',
            'theme.colors.accent' => '#ea580c',
        ]);

        $palette = Theme::chartPalette();

        $this->assertSame('#c026d3', $palette[0]);
        $this->assertSame('#ea580c', $palette[1]);
        $this->assertGreaterThan(2, count($palette), '3 色目以降の固定色も含まれる');
    }

    #[Test]
    public function an_invalid_color_falls_back_to_the_default(): void
    {
        // <style> に直接埋め込むため、CSS として不正な値は通さない
        config(['theme.colors.primary' => 'red; } body { display:none']);

        $this->assertSame('#4f46e5', Theme::primary());
        $this->assertStringNotContainsString('display:none', (string) Theme::cssVariables());
    }

    #[Test]
    public function a_logo_path_is_resolved_against_the_public_directory(): void
    {
        $this->assertNull(Theme::logoUrl(), '未設定なら null（頭文字マークを表示する）');

        config(['theme.logo' => 'images/logo.svg']);
        $this->assertSame(asset('images/logo.svg'), Theme::logoUrl());

        config(['theme.logo' => 'https://example.com/logo.png']);
        $this->assertSame('https://example.com/logo.png', Theme::logoUrl());
    }

    #[Test]
    public function the_initial_is_taken_from_the_service_name(): void
    {
        config(['theme.name' => '販売管理システム']);

        $this->assertSame('販', Theme::initial());
    }
}
