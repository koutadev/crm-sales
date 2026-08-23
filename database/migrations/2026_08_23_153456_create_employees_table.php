<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 社員マスタ。コードは EMP-0001 形式。
 *
 * 勤怠・CRM の担当者など、今後の各システムから参照される中核マスタ。
 * ログインユーザー(users)とは 1:1 で任意に紐付けられる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);

            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email', 255)->nullable()->unique();

            // 在籍状態 (App\Enums\EmploymentStatus)
            $table->string('employment_status', 16)->default('active')->index();

            // ログインユーザーとの紐付け(任意・1 社員 = 1 ユーザー)
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->masterColumns();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
