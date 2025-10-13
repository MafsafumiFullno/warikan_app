<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->id()->comment('サロゲートキー');
            $table->unsignedBigInteger('project_id')->comment('プロジェクトID');
            $table->unsignedBigInteger('project_member_id')->comment('プロジェクト内メンバーID（プロジェクト毎に1から開始）');
            $table->unsignedBigInteger('customer_id')->nullable()->comment('顧客ID（nullの場合はメールアドレスなしメンバー）');
            $table->string('member_name')->nullable()->comment('メンバー名（customer_idがnullの場合に使用）');
            $table->string('member_email')->nullable()->comment('メンバーメールアドレス（customer_idがnullの場合に使用）');
            $table->text('memo')->nullable()->comment('メンバー情報の補足');
            $table->unsignedBigInteger('role_id')->comment('ロールID');
            $table->decimal('split_weight', 8, 2)->default(1.00)->comment('割り勘比重（デフォルト1.00）');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();

            // 外部キー制約
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->foreign('role_id')->references('role_id')->on('project_roles')->onDelete('restrict');
            
            // 複合ユニーク制約（同じプロジェクトに同じメンバーは1回のみ）
            $table->unique(['project_id', 'customer_id'], 'project_member_unique');
            // プロジェクト内でのproject_member_idのユニーク制約
            $table->unique(['project_id', 'project_member_id'], 'project_member_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};
