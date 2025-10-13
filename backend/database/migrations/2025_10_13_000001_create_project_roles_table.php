<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_roles', function (Blueprint $table) {
            $table->id('role_id')->comment('ロールID');
            $table->string('role_code')->unique()->comment('ロールコード');
            $table->string('role_name')->comment('ロール名');
            $table->text('description')->nullable()->comment('説明');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();
        });

        // 初期データを挿入
        DB::table('project_roles')->insert([
            [
                'role_code' => 'owner',
                'role_name' => 'オーナー',
                'description' => 'プロジェクトの所有者。すべての権限を持つ',
                'del_flg' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_code' => 'member',
                'role_name' => 'メンバー',
                'description' => 'プロジェクトの一般メンバー',
                'del_flg' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_roles');
    }
};
