<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id('task_id')->comment('タスクID');
            $table->foreignId('project_id')->constrained('projects', 'project_id')->comment('プロジェクトID');
            $table->smallInteger('project_task_code')->comment('プロジェクトタスクコード');
            $table->string('task_name')->nullable()->comment('タスク名');
            $table->string('task_member_name')->nullable()->comment('タスクメンバー名');
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->comment('顧客ID');
            $table->decimal('accounting_amount', 10, 2)->comment('金額');
            $table->string('accounting_type')->comment('会計タイプ');
            $table->text('breakdown')->nullable()->comment('内訳');
            $table->string('payment_id')->nullable()->comment('支払いID');
            $table->text('memo')->nullable()->comment('メモ');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();

            // インデックスの追加
            $table->index(('accounting_type'), 'idx_accounting_type');
            $table->index(('payment_id'), 'idx_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};