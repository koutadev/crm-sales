<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 部署マスタ(社員マスタのサブマスタ)。コードは DEP-0001 形式。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->masterColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
