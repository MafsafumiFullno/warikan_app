<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Services\BaseService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

class AuthService extends BaseService
{
    protected Hasher $hasher;

    public function __construct(
        Hasher $hasher,
        ValidationFactory $validationFactory,
        ConnectionInterface $db,
        LoggerInterface $logger
    ) {
        parent::__construct($validationFactory, $db, $logger);
        $this->hasher = $hasher;
    }

    /**
     * ゲストユーザーとしてログイン
     */
    public function guestLogin(array $data): array
    {
        $this->logInfo('ゲストログイン開始', ['request_data' => $data]);

        $validated = $this->validateData($data, [
            'nick_name' => 'nullable|string|max:255',
        ]);

        // 自動的にユニークなニックネームを生成
        $nickName = $validated['nick_name'] ?? $this->generateGuestNickname();

        $this->logInfo('ゲストユーザー作成開始', ['nick_name' => $nickName]);

        $customer = Customer::create([
            'is_guest' => true,
            'nick_name' => $nickName,
        ]);

        $this->logInfo('ゲストユーザー作成成功', ['customer_id' => $customer->customer_id]);

        $token = $customer->createToken('guest-token')->plainTextToken;

        $this->logInfo('ゲストログイン成功', ['customer_id' => $customer->customer_id]);

        return $this->successResponse('ゲストログインに成功しました', [
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * 会員登録（メール/パスワード）
     */
    public function register(array $data): array
    {
        $validated = $this->validateData($data, [
            'email' => 'required|email|unique:customers,email',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
        ]);

        $customer = Customer::create([
            'is_guest' => false,
            'email' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nick_name' => $validated['nick_name'],
            'password' => $this->hasher->make($validated['password']),
        ]);

        $token = $customer->createToken('auth-token')->plainTextToken;

        return $this->successResponse('会員登録に成功しました', [
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
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

        $validated = $this->validateData($data, [
            'email' => 'required|email|unique:customers,email,' . $customer->customer_id . ',customer_id',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
        ]);

        // 既存のゲストアカウントを更新
        $customer->update([
            'is_guest' => false,
            'email' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nick_name' => $validated['nick_name'],
            'password' => $this->hasher->make($validated['password']),
        ]);

        return $this->successResponse('会員登録に成功しました', [
            'customer' => $customer->fresh(),
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * ログイン（メール/パスワード）
     */
    public function login(array $data): array
    {
        $validated = $this->validateData($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = Customer::where('email', $validated['email'])->first();
        if (!$customer || !$customer->password || !$this->hasher->check($validated['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['メールアドレスまたはパスワードが正しくありません。'],
            ]);
        }

        $token = $customer->createToken('auth-token')->plainTextToken;

        return $this->successResponse('ログインに成功しました', [
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
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
        return $this->successResponse('ユーザー情報を取得しました', [
            'customer' => $customer
        ]);
    }

    /**
     * ユーザー情報更新
     */
    public function updateProfile(Customer $customer, array $data): array
    {
        $validated = $this->validateData($data, [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:customers,email,' . $customer->customer_id . ',customer_id',
            'password' => 'nullable|string|min:8',
        ]);

        $updateData = $this->removeNullValues($validated);
        
        if (isset($updateData['password'])) {
            $updateData['password'] = $this->hasher->make($updateData['password']);
        }

        if (empty($updateData)) {
            throw new \InvalidArgumentException('更新するデータがありません');
        }

        $customer->update($updateData);

        return $this->successResponse('プロフィールを更新しました', [
            'customer' => $customer->fresh()
        ]);
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
