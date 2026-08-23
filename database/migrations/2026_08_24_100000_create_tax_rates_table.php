<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 税率マスタ。
 *
 * 税率が変わったときは既存レコードを書き換えず、適用開始日(effective_from)の
 * 新しいレコードを追加して世代管理する(例: 2026-10-01 から 12%)。
 * コードは持たない(名称 + 税率 + 適用開始日で識別する)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();

            // 標準 / 軽減 など。同じ名称で適用開始日の異なるレコードが並ぶ
            $table->string('name', 50);

            // 税率(%)。金額計算の誤差を避けるため整数で保持する
            $table->unsignedSmallInteger('rate_percent');

            $table->date('effective_from');

            $table->masterColumns();

            // 一覧の既定の並び(適用開始日の降順)と、適用中の世代の検索に使う
            $table->index(['name', 'effective_from']);
            $table->index('effective_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
