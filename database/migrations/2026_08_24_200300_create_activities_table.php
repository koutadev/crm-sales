<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 活動履歴(電話 / 訪問 / メール / メモ)。
 *
 * 顧客(partners)には必ず紐づき、商談(deals)への紐付けは任意。
 * 「顧客への活動」と「商談の活動」の両方を 1 テーブルで扱う。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('partner_id')->constrained()->restrictOnDelete();

            // 商談に紐づかない活動(顧客への定期連絡など)もあるため任意
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();

            // 実施者(自社の社員)
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            // 活動種別 (App\Enums\ActivityType): 電話 / 訪問 / メール / メモ
            $table->string('type', 16)->index();

            $table->dateTime('activity_at')->index();
            $table->text('note')->nullable();

            $table->masterColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
