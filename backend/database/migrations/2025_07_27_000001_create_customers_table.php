<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id('customer_id')->comment('顧客ID');
            $table->boolean('is_guest')->default(false)->comment('ゲストフラグ');
            $table->string('first_name')->nullable()->comment('名');
            $table->string('last_name')->nullable()->comment('姓');
            $table->string('nick_name')->nullable()->comment('ニックネーム');
            $table->string('email')->unique()->nullable()->comment('メールアドレス');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
