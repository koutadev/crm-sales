<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 店舗の所在都道府県。
 *
 * 階層（地域 > エリア > 店舗）は 3 段のままにして、都道府県は店舗の属性として持つ。
 * こうしておくと「同じ都道府県の店舗をまとめて見る」を、階層を深くせずに実現できる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('prefecture', 8)->nullable()->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['prefecture']);
            $table->dropColumn('prefecture');
        });
    }
};
