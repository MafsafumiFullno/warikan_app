<?php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('split_methods_master', function (Blueprint $table) {
            $table->id('split_method_id')->comment('割り勘方法ID');
            $table->string('split_method_name')->nullable()->comment('割り勘方法名');
            $table->json('split_rules')->nullable()->comment('割り勘ルールのJSON');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('split_methods_master');
    }
};