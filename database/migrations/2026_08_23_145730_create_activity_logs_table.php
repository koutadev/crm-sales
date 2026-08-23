<?php

use App\Models\ActivityLog;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 操作ログ(誰が・いつ・何を・どう変更したか)を 1 テーブルで記録する。
 *
 * @see ActivityLog
 * @see LogsActivity
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // 実行ユーザー。バッチ処理など未ログイン時は null
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // created / updated / deleted / restored / force_deleted / logged_in / logged_out …
            $table->string('action', 32);

            // 対象モデル。ログイン等モデルを伴わない操作では null
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // 一覧で人が読める見出し(対象が削除された後も残す)
            $table->string('subject_label')->nullable();

            // 変更内容(更新なら変更後の値、作成なら初期値)
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
