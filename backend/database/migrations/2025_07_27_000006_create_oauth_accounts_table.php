<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->comment('顧客ID');
            $table->string('provider_name')->comment('OAuthプロバイダー名');
            $table->string('provider_user_id')->comment('プロバイダー側ユーザーID');
            $table->string('access_token')->nullable()->comment('アクセストークン');
            $table->string('refresh_token')->nullable()->comment('リフレッシュトークン');
            $table->timestamp('token_expired_date')->nullable()->comment('トークン有効期限');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_accounts');
    }
};
