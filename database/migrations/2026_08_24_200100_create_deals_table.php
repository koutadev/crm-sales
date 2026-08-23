<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商談(案件)。コードは年度別採番で DEAL-2026-0001 形式。
 *
 * 金額は明細(deal_items)の税込金額の合算を amount_total に非正規化して持つ。
 * 一覧・ダッシュボードの集計を明細の再集計なしに引けるようにするためで、
 * 再計算そのものは明細の実装(STEP 4)で入れる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();

            $table->foreignId('partner_id')->constrained()->restrictOnDelete();

            // 先方担当(任意)
            $table->foreignId('partner_contact_id')->nullable()->constrained()->nullOnDelete();

            // 自社の営業担当
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            $table->string('title', 191);

            // 商談ステータス (App\Enums\DealStatus): 見込み / 提案中 / 見積提示 / 受注 / 失注
            $table->string('status', 16)->default('prospect')->index();

            // 確度(%)
            $table->unsignedTinyInteger('probability')->default(0);

            // 税込合計(明細の税込金額の合算)。内税統一のため税込を正として持つ
            $table->unsignedBigInteger('amount_total')->default(0);

            $table->date('expected_close_date');

            // 受注日(受注したときだけ入る)
            $table->date('ordered_at')->nullable();

            $table->masterColumns();

            $table->index('title');
            $table->index(['status', 'expected_close_date']);
            $table->index('ordered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
