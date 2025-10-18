<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            // customer_idの外部キー制約を削除
            $table->dropForeign(['customer_id']);
            
            // customer_idカラムを削除
            $table->dropColumn('customer_id');
            
            // member_idカラムを追加
            $table->unsignedBigInteger('member_id')->nullable()->after('task_member_name')->comment('プロジェクトメンバーID');
            
            // member_idの外部キー制約を追加
            $table->foreign('member_id')->references('id')->on('project_members')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            // member_idの外部キー制約を削除
            $table->dropForeign(['member_id']);
            
            // member_idカラムを削除
            $table->dropColumn('member_id');
            
            // customer_idカラムを追加
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->comment('顧客ID');
        });
    }
};
