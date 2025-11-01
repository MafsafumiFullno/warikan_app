<?php

namespace Tests\Unit\Service\Auth;

use App\Models\Customer;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestAuthService extends TestCase
{
    use RefreshDatabase;

    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    // ===== ゲストユーザーとしてログインテスト =====

    /**
     * ニックネームなしでゲストログイン成功
     */
    public function test_guest_login_success_without_nickname(): void
    {
        $result = $this->authService->guestLogin([]);

        $this->assertTrue($result['success']);
        $this->assertEquals('ゲストログインに成功しました', $result['message']);
        $this->assertArrayHasKey('customer', $result['data']);
        $this->assertArrayHasKey('token', $result['data']);
        $this->assertEquals('Bearer', $result['data']['token_type']);

        $customer = $result['data']['customer'];
        $this->assertTrue($customer->is_guest);
        $this->assertNotNull($customer->nick_name);
        $this->assertMatchesRegularExpression('/^[^\s]{1,}\d+$/', $customer->nick_name);
    }

    /**
     * ニックネーム指定でゲストログイン成功
     */
    public function test_guest_login_success_with_nickname(): void
    {
        $nickName = 'テストユーザー';
        $result = $this->authService->guestLogin(['nick_name' => $nickName]);

        $this->assertTrue($result['success']);
        $customer = $result['data']['customer'];
        $this->assertEquals($nickName, $customer->nick_name);
        $this->assertTrue($customer->is_guest);
    }

    // ===== 会員登録（メール/パスワード）テスト =====

    /**
     * 正常な会員登録成功
     */
    public function test_register_success(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'first_name' => '太郎',
            'last_name' => '田中',
            'nick_name' => 'タロちゃん',
        ];

        $result = $this->authService->register($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('会員登録に成功しました', $result['message']);
        $this->assertArrayHasKey('customer', $result['data']);
        $this->assertArrayHasKey('token', $result['data']);

        $customer = $result['data']['customer'];
        $this->assertFalse($customer->is_guest);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('太郎', $customer->first_name);
        $this->assertEquals('田中', $customer->last_name);
        $this->assertEquals('タロちゃん', $customer->nick_name);
    }

    /**
     * メールアドレス未指定で会員登録失敗
     */
    public function test_register_with_missing_email_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->register([
            'password' => 'password123',
        ]);
    }

    /**
     * 無効なメールアドレス形式で会員登録失敗
     */
    public function test_register_with_invalid_email_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->register([
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);
    }

    /**
     * パスワードが短すぎて会員登録失敗
     */
    public function test_register_with_short_password_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->register([
            'email' => 'test@example.com',
            'password' => 'short',
        ]);
    }

    /**
     * 重複メールアドレスで会員登録失敗
     */
    public function test_register_with_duplicate_email_throws_validation_exception(): void
    {
        Customer::create([
            'email' => 'duplicate@example.com',
            'password' => bcrypt('password123'),
            'is_guest' => false,
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->register([
            'email' => 'duplicate@example.com',
            'password' => 'password123',
        ]);
    }

    // ===== ゲストユーザーを会員にアップグレードテスト =====

    /**
     * ゲストユーザーの会員アップグレード成功
     */
    public function test_upgrade_to_member_success(): void
    {
        $guestCustomer = Customer::create([
            'is_guest' => true,
            'nick_name' => 'ゲストユーザー',
        ]);

        $data = [
            'email' => 'upgrade@example.com',
            'password' => 'password123',
            'first_name' => '次郎',
            'last_name' => '佐藤',
            'nick_name' => 'ジロー',
        ];

        $result = $this->authService->upgradeToMember($guestCustomer, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals('会員登録に成功しました', $result['message']);
        
        $guestCustomer->refresh();
        $this->assertFalse($guestCustomer->is_guest);
        $this->assertEquals('upgrade@example.com', $guestCustomer->email);
        $this->assertEquals('次郎', $guestCustomer->first_name);
        $this->assertEquals('佐藤', $guestCustomer->last_name);
    }

    /**
     * 既に会員のユーザーをアップグレードしようとして失敗
     */
    public function test_upgrade_to_member_when_already_member_throws_exception(): void
    {
        $memberCustomer = Customer::create([
            'is_guest' => false,
            'email' => 'member@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('既に会員登録済みです');

        $this->authService->upgradeToMember($memberCustomer, [
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);
    }

    /**
     * メールアドレス未指定でゲストアップグレード失敗
     */
    public function test_upgrade_to_member_with_missing_email_throws_validation_exception(): void
    {
        $guestCustomer = Customer::create([
            'is_guest' => true,
            'nick_name' => 'ゲストユーザー',
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->upgradeToMember($guestCustomer, [
            'password' => 'password123',
        ]);
    }

    // ===== ログイン（メール/パスワード）テスト =====

    /**
     * 正常なログイン成功
     */
    public function test_login_success(): void
    {
        $password = 'password123';
        $customer = Customer::create([
            'is_guest' => false,
            'email' => 'login@example.com',
            'password' => bcrypt($password),
        ]);

        $result = $this->authService->login([
            'email' => 'login@example.com',
            'password' => $password,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('ログインに成功しました', $result['message']);
        $this->assertArrayHasKey('customer', $result['data']);
        $this->assertArrayHasKey('token', $result['data']);
        $this->assertEquals($customer->customer_id, $result['data']['customer']->customer_id);
    }

    /**
     * 存在しないメールアドレスでログイン失敗
     */
    public function test_login_with_invalid_email_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->login([
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);
    }

    /**
     * 間違ったパスワードでログイン失敗
     */
    public function test_login_with_wrong_password_throws_validation_exception(): void
    {
        Customer::create([
            'is_guest' => false,
            'email' => 'wrongpass@example.com',
            'password' => bcrypt('correctpassword'),
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->login([
            'email' => 'wrongpass@example.com',
            'password' => 'wrongpassword',
        ]);
    }

    /**
     * ゲストアカウントでログイン失敗
     */
    public function test_login_with_guest_account_throws_validation_exception(): void
    {
        Customer::create([
            'is_guest' => true,
            'nick_name' => 'ゲストユーザー',
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->login([
            'email' => 'guest@example.com',
            'password' => 'password123',
        ]);
    }

    // ===== ログアウトテスト =====

    /**
     * ログアウトでトークン削除成功
     */
    public function test_logout_deletes_tokens(): void
    {
        $customer = Customer::create([
            'is_guest' => true,
            'nick_name' => 'ゲストユーザー',
        ]);

        $token = $customer->createToken('auth-token');

        $this->assertCount(1, $customer->tokens);

        $this->authService->logout($customer);

        $customer->refresh();
        $this->assertCount(0, $customer->tokens);
    }

    // ===== ユーザー情報取得テスト =====

    /**
     * ユーザー情報取得成功
     */
    public function test_get_user_success(): void
    {
        $customer = Customer::create([
            'is_guest' => false,
            'email' => 'user@example.com',
            'nick_name' => 'ユーザー',
        ]);

        $result = $this->authService->getUser($customer);

        $this->assertTrue($result['success']);
        $this->assertEquals('ユーザー情報を取得しました', $result['message']);
        $this->assertEquals($customer->customer_id, $result['data']['customer']->customer_id);
    }

    // ===== ユーザー情報更新テスト =====

    /**
     * プロフィール更新成功
     */
    public function test_update_profile_success(): void
    {
        $customer = Customer::create([
            'is_guest' => false,
            'email' => 'update@example.com',
            'nick_name' => '古い名前',
            'password' => bcrypt('oldpassword'),
        ]);

        $result = $this->authService->updateProfile($customer, [
            'nick_name' => '新しい名前',
            'first_name' => '新太郎',
            'last_name' => '新田',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('プロフィールを更新しました', $result['message']);
        
        $customer->refresh();
        $this->assertEquals('新しい名前', $customer->nick_name);
        $this->assertEquals('新太郎', $customer->first_name);
        $this->assertEquals('新田', $customer->last_name);
    }

    /**
     * パスワード更新成功
     */
    public function test_update_profile_with_password(): void
    {
        $customer = Customer::create([
            'is_guest' => false,
            'email' => 'updatepass@example.com',
            'password' => bcrypt('oldpassword'),
        ]);

        $oldPasswordHash = $customer->password;

        $result = $this->authService->updateProfile($customer, [
            'password' => 'newpassword123',
        ]);

        $this->assertTrue($result['success']);
        
        $customer->refresh();
        $this->assertNotEquals($oldPasswordHash, $customer->password);
    }

    /**
     * 既存メールアドレスでプロフィール更新失敗
     */
    public function test_update_profile_with_existing_email_throws_validation_exception(): void
    {
        Customer::create([
            'is_guest' => false,
            'email' => 'exists@example.com',
            'password' => bcrypt('password'),
        ]);

        $customer = Customer::create([
            'is_guest' => false,
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->updateProfile($customer, [
            'email' => 'exists@example.com',
        ]);
    }

    /**
     * 更新データなしでプロフィール更新失敗
     */
    public function test_update_profile_with_empty_data_throws_exception(): void
    {
        $customer = Customer::create([
            'is_guest' => false,
            'email' => 'empty@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('更新するデータがありません');

        $this->authService->updateProfile($customer, []);
    }

    /**
     * 同じメールアドレスでプロフィール更新成功
     */
    public function test_update_profile_with_self_email_success(): void
    {
        $customer = Customer::create([
            'is_guest' => false,
            'email' => 'self@example.com',
            'password' => bcrypt('password'),
        ]);

        $result = $this->authService->updateProfile($customer, [
            'email' => 'self@example.com',
        ]);

        $this->assertTrue($result['success']);
    }
}