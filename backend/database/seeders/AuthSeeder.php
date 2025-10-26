<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;

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
            Customer::create($customerData);
        }
    }
}

