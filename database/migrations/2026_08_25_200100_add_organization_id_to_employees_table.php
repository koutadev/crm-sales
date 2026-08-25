<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 社員の所属組織（店舗）。
 *
 * 売上などの集計は「担当者 → 所属店舗 → エリア → 地域」とたどるので、
 * 伝票側のテーブルには組織を持たせない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('position_id')
                ->constrained('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
