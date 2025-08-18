<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\OAuthAccount;

class AuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // テスト用の顧客データを作成
        $customers = [
            [
                'is_guest' => false,
                'first_name' => '田中',
                'last_name' => '太郎',
                'nick_name' => 'タロウ',
                'email' => 'tanaka@example.com',
                'del_flg' => false,
            ],
            [
                'is_guest' => false,
                'first_name' => '佐藤',
                'last_name' => '花子',
                'nick_name' => 'ハナコ',
                'email' => 'sato@example.com',
                'del_flg' => false,
            ],
            [
                'is_guest' => true,
                'first_name' => null,
                'last_name' => null,
                'nick_name' => 'ゲストユーザー',
                'email' => null,
                'del_flg' => false,
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Customer::create($customerData);
            
            // 最初の2つの顧客にはOAuthアカウントを作成
            if (!$customer->is_guest) {
                OAuthAccount::create([
                    'customer_id' => $customer->customer_id,
                    'provider_name' => 'google',
                    'provider_user_id' => 'google_' . $customer->customer_id,
                    'access_token' => 'test_access_token_' . $customer->customer_id,
                    'refresh_token' => 'test_refresh_token_' . $customer->customer_id,
                    'token_expired_date' => now()->addDays(30),
                ]);
            }
        }
    }
}

