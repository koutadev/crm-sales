<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 取引先マスタ。コードは PTR-0001 形式。
 *
 * CRM の顧客、受発注の得意先 / 仕入先は、いずれこのテーブルを親として参照する想定。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 191);

            // 取引先区分 (App\Enums\PartnerType): 得意先 / 仕入先 / 両方
            $table->string('partner_type', 16)->index();

            // 法人 / 個人 (App\Enums\EntityType)
            $table->string('entity_type', 16)->index();

            // 連絡先
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('address', 255)->nullable();

            $table->masterColumns();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
