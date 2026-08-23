<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 取引先担当者(先方の窓口となる個人)。
 *
 * 会社(partners)にぶら下がる。商談の「先方担当」として参照される。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();

            $table->masterColumns();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contacts');
    }
};
