<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 商品マスタの標準単価を「税込・整数(最小通貨単位)」に揃える。
 *
 * 内税統一(docs/basic-design.md 6章)により、単価は税込を正として扱う。
 * 商談明細は商品の単価をコピーして保持するため、明細の画面を作る前に型を揃えておく。
 *
 * 既存データは四捨五入して整数化する(小数を持つ運用はまだ無い)。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE products ALTER COLUMN unit_price DROP DEFAULT');
        DB::statement('ALTER TABLE products ALTER COLUMN unit_price TYPE bigint USING round(unit_price)::bigint');
        DB::statement('ALTER TABLE products ALTER COLUMN unit_price SET DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products ALTER COLUMN unit_price DROP DEFAULT');
        DB::statement('ALTER TABLE products ALTER COLUMN unit_price TYPE numeric(12,2) USING unit_price::numeric(12,2)');
        DB::statement('ALTER TABLE products ALTER COLUMN unit_price SET DEFAULT 0');
    }
};
