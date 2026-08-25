<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一覧の保存ビュー(マイビュー)。
 *
 * よく使う絞り込みの組み合わせに名前を付けて保存し、プルダウンから呼び出す。
 * ユーザーごとに持ち、他人のビューは見えない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // どの一覧のビューか(TableDefinition::key())
            $table->string('table_key', 64);
            $table->string('name', 100);
            // 一覧の絞り込み条件(URL のクエリと同じ形)
            $table->json('conditions');
            // 条件なしで開いたときに自動で適用するビュー
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'table_key', 'name']);
            $table->index(['user_id', 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
