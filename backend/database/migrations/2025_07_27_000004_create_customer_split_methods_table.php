<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_split_methods', function (Blueprint $table) {
            $table->id('split_method_id')->comment('割り勘方法ID');
            $table->text('description')->nullable()->comment('割り勘方法の説明');
            $table->string('template_type')->comment('テンプレートの種類');
            $table->foreignId('customer_id')->constrained('customers')->comment('顧客ID');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();

            // インデックスの追加
            $table->index('customer_id', 'idx_customer_id');
            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_split_methods');
    }
};