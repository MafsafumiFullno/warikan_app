<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_members', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->foreignId('member_id')->constrained('project_members', 'id')->comment('プロジェクトメンバーID');
            $table->foreignId('task_id')->constrained('project_tasks', 'task_id')->comment('プロジェクトタスクID');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();

            // インデックスの追加
            $table->index(['member_id', 'task_id'], 'idx_member_task');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_members');
    }
};
