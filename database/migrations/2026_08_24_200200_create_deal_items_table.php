<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商談明細。過去データ保全の要。
 *
 * 明細を作った時点の「税込単価」と「税率(%)」をコピー保持し、
 * 以後に商品マスタ・税率マスタが変わっても確定済みの金額が動かないようにする。
 *
 * 金額は税込(amount_incl_tax)が正。消費税額・税抜金額は
 *   消費税額 = 税込金額 - 税込金額 ÷ (1 + 税率)  ※明細単位・切り捨て
 * で逆算した結果を保存する(計算の実装は STEP 4)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            // 税込単価(明細作成時に商品マスタからコピー)
            $table->unsignedBigInteger('unit_price')->default(0);

            // 確定時点の税率。id は追跡用、正となるのは % のスナップショット
            $table->foreignId('tax_rate_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('tax_rate_percent')->default(0);

            // 税込が正。消費税・税抜は税込からの逆算値
            $table->unsignedBigInteger('amount_incl_tax')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('amount_excl_tax')->default(0);

            $table->masterColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_items');
    }
};
