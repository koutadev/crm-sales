<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 組織マスタ（地域 > エリア > 店舗）。
 *
 * 自己参照の親子で 3 段を表す。段数は OrganizationType で決まっており、
 * 「地域の親は無し／エリアの親は地域／店舗の親はエリア」を保存時に検証する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();     // ORG-0001
            $table->string('name', 100);
            $table->string('type', 16)->index();      // region / area / store
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->nullOnDelete();

            $table->masterColumns();

            $table->index('name');
            $table->index(['type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
