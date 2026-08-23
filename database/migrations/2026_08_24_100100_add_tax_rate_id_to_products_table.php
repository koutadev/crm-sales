<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品マスタに標準税率(税率マスタへの参照)を追加する。
 *
 * 未選択のまま保存された場合は既定の標準税率が入る(App\Models\Product::booted)。
 * 参照されている税率を消せないよう、削除は制限する(税率マスタ側は論理削除で運用する)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->after('unit_price')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
        });
    }
};
