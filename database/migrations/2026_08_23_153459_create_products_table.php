<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品マスタ。コードは PRD-0001 形式。
 *
 * 受発注の明細、EC の商品はこのテーブルを親として参照する想定。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 191);

            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();

            // 標準単価。通貨計算の誤差を避けるため decimal を使う
            $table->decimal('unit_price', 12, 2)->default(0);

            // 単位(個 / 式 / kg など)
            $table->string('unit', 16)->nullable();

            $table->masterColumns();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
