<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 売上目標（予実管理の「予」）。
 *
 * 商談・明細には一切手を入れず、独立したテーブルとして持つ。
 * 実績はこれまでどおり商談の受注金額から集計し、達成率は「実績 ÷ 目標」で出す。
 *
 * 粒度は 全社 / 地域 / エリア / 店舗 / 担当者。
 * 期間は年月（月次）で持ち、四半期・年度はその合計として扱う。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();       // TGT-0001

            // 対象（全社のときは target_id が null）
            $table->string('scope', 16)->index();
            $table->unsignedBigInteger('target_id')->nullable();

            // 対象期間（年月。年度で入れる場合も月に割って持つ）
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // 目標金額（税込・円）
            $table->unsignedBigInteger('amount');

            $table->masterColumns();

            // 同じ対象・同じ年月の目標は 1 本だけ
            $table->unique(['scope', 'target_id', 'year', 'month'], 'sales_targets_unique_period');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
