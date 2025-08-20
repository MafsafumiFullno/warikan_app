<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id('project_id')->comment('プロジェクトID');
            $table->foreignId('customer_id')->constrained('customers')->comment('顧客ID');
            $table->string('project_name')->comment('プロジェクト名');
            $table->text('description')->nullable()->comment('プロジェクトの説明');
            $table->string('project_status', 32)->default('draft')->comment('プロジェクトステータス');
            $table->foreignId('split_method_id')->nullable()->constrained('customer_split_methods')->comment('割り勘方法ID');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();

            // インデックスの追加
            $table->index(('customer_id'), 'idx_customer_id');
            $table->index(('split_method_id'), 'idx_split_method_id');
            $table->index(('project_status'), 'idx_project_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};