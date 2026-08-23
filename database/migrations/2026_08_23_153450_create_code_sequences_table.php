<?php

use App\Support\Code\CodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 業務コードの採番カウンタ。
 *
 * @see CodeGenerator
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_sequences', function (Blueprint $table) {
            // 採番系列。通常は対象のテーブル名(employees / partners / …)
            $table->string('key', 64)->primary();

            // 次に払い出す番号
            $table->unsignedBigInteger('next_number')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_sequences');
    }
};
