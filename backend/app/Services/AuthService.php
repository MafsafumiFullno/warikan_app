<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

class AuthService
{
    protected Hasher $hasher;
    protected ValidationFactory $validationFactory;
    protected LoggerInterface $logger;

    public function __construct(
        Hasher $hasher,
        ValidationFactory $validationFactory,
        LoggerInterface $logger
    ) {
        $this->hasher = $hasher;
        $this->validationFactory = $validationFactory;
        $this->logger = $logger;
    }

    /**
     * ゲストユーザーとしてログイン
     */
    public function guestLogin(array $data): array
    {
        $this->logger->info('ゲストログイン開始', ['request_data' => $data]);

        $validator = $this->validationFactory->make($data, [
            'nick_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $this->logger->error('ゲストログインバリデーションエラー', ['errors' => $validator->errors()]);
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // 自動的にユニークなニックネームを生成
        $nickName = $data['nick_name'] ?? $this->generateGuestNickname();

        $this->logger->info('ゲストユーザー作成開始', ['nick_name' => $nickName]);

        $customer = Customer::create([
            'is_guest' => true,
            'nick_name' => $nickName,
        ]);

        $this->logger->info('ゲストユーザー作成成功', ['customer_id' => $customer->customer_id]);

        $token = $customer->createToken('guest-token')->plainTextToken;

        $this->logger->info('ゲストログイン成功', ['customer_id' => $customer->customer_id]);

        return [
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * 会員登録（メール/パスワード）
     */
    public function register(array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'email' => 'required|email|unique:customers,email',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $customer = Customer::create([
            'is_guest' => false,
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'nick_name' => $data['nick_name'],
            'password' => $this->hasher->make($data['password']),
        ]);

        $token = $customer->createToken('auth-token')->plainTextToken;

        return [
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * ゲストユーザーを会員にアップグレード
     */
    public function upgradeToMember(Customer $customer, array $data): array
    {
        // ゲストユーザーでない場合はエラー
        if (!$customer->is_guest) {
            throw new \InvalidArgumentException('既に会員登録済みです');
        }

        $validator = $this->validationFactory->make($data, [
            'email' => 'required|email|unique:customers,email,' . $customer->customer_id . ',customer_id',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // 既存のゲストアカウントを更新
        $customer->update([
            'is_guest' => false,
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'nick_name' => $data['nick_name'],
            'password' => $this->hasher->make($data['password']),
        ]);

        return [
            'customer' => $customer->fresh(),
            'token_type' => 'Bearer'
        ];
    }

    /**
     * ログイン（メール/パスワード）
     */
    public function login(array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $customer = Customer::where('email', $data['email'])->first();
        if (!$customer || !$customer->password || !$this->hasher->check($data['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['メールアドレスまたはパスワードが正しくありません。'],
            ]);
        }

        $token = $customer->createToken('auth-token')->plainTextToken;

        return [
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * ログアウト
     */
    public function logout(Customer $customer): void
    {
        $customer->tokens()->where('name', 'auth-token')->delete();
    }

    /**
     * ユーザー情報取得
     */
    public function getUser(Customer $customer): array
    {
        return [
            'customer' => $customer
        ];
    }

    /**
     * ユーザー情報更新
     */
    public function updateProfile(Customer $customer, array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:customers,email,' . $customer->customer_id . ',customer_id',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $updateData = [];
        
        if (isset($data['first_name']) && $data['first_name'] !== null) {
            $updateData['first_name'] = $data['first_name'];
        }
        if (isset($data['last_name']) && $data['last_name'] !== null) {
            $updateData['last_name'] = $data['last_name'];
        }
        if (isset($data['nick_name']) && $data['nick_name'] !== null) {
            $updateData['nick_name'] = $data['nick_name'];
        }
        if (isset($data['email']) && $data['email'] !== null) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['password']) && $data['password'] !== null) {
            $updateData['password'] = $this->hasher->make($data['password']);
        }

        if (empty($updateData)) {
            throw new \InvalidArgumentException('更新するデータがありません');
        }

        $customer->update($updateData);

        return [
            'customer' => $customer->fresh()
        ];
    }

    /**
     * ゲストユーザーのニックネームを自動生成
     */
    private function generateGuestNickname(): string
    {
        $adjectives = ['楽しい', '元気な', '素敵な', '素晴らしい', '素朴な', '勇敢な', '賢い', '優しい'];
        $animals = ['ねこ', 'いぬ', 'うさぎ', 'パンダ', 'ライオン', 'ぞう', 'きつね', 'たぬき'];
        
        $adjective = $adjectives[array_rand($adjectives)];
        $animal = $animals[array_rand($animals)];
        $number = rand(1, 999);
        
        return "{$adjective}{$animal}{$number}";
    }
}
