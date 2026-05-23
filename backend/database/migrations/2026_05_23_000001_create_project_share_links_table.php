<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_share_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('プロジェクトID');
            $table->string('token_hash', 64)->unique()->comment('共有トークンハッシュ');
            $table->text('token_encrypted')->comment('共有トークン暗号化文字列');
            $table->string('permission', 32)->default('owner_only')->comment('共有リンク権限');
            $table->unsignedBigInteger('created_by_customer_id')->comment('作成者顧客ID');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();

            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            $table->foreign('created_by_customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->unique('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_share_links');
    }
};
