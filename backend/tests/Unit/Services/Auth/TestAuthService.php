<?php

namespace Tests\Unit\Services\Auth;

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

    private function createGuest(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'is_guest' => true,
            'nick_name' => 'ゲストユーザー',
        ], $overrides));
    }

    private function createMember(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'is_guest' => false,
            'email' => 'member@example.com',
            'password' => bcrypt('password123'),
        ], $overrides));
    }

    private function baseRegisterData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'password' => 'password123',
            'first_name' => '太郎',
            'last_name' => '田中',
            'nick_name' => 'タロちゃん',
        ], $overrides);
    }

    private function baseUpgradeData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'upgrade@example.com',
            'password' => 'password123',
            'first_name' => '次郎',
            'last_name' => '佐藤',
            'nick_name' => 'ジロー',
        ], $overrides);
    }

    // ===== ゲストユーザーとしてログインテスト =====

    /**
     * ニックネームなしでゲストログイン成功
     */
    public function test_guest_login_success_without_nickname(): void
    {
        $result = $this->authService->guestLogin([]);

        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);

        $customer = $result['customer'];
        $this->assertTrue($customer->is_guest);
        $this->assertNotNull($customer->nick_name);
        $this->assertMatchesRegularExpression('/^[^\s]{1,}\d+$/', $customer->nick_name);
        $this->assertNotEmpty($result['token']);
    }

    /**
     * ニックネーム指定でゲストログイン成功
     */
    public function test_guest_login_success_with_nickname(): void
    {
        $nickName = 'テストユーザー';
        $result = $this->authService->guestLogin(['nick_name' => $nickName]);

        $customer = $result['customer'];
        $this->assertEquals($nickName, $customer->nick_name);
        $this->assertTrue($customer->is_guest);
    }

    /**
     * ニックネームが256文字でゲストログイン失敗
     */
    public function test_guest_login_with_too_long_nickname_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->guestLogin([
            'nick_name' => str_repeat('a', 256),
        ]);
    }

    // ===== 会員登録（メール/パスワード）テスト =====

    /**
     * 正常な会員登録成功
     */
    public function test_register_success(): void
    {
        $result = $this->authService->register($this->baseRegisterData());

        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);

        $customer = $result['customer'];
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

        $this->authService->register($this->baseRegisterData([
            'email' => null,
        ]));
    }

    /**
     * 無効なメールアドレス形式で会員登録失敗
     */
    public function test_register_with_invalid_email_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->register($this->baseRegisterData([
            'email' => 'invalid-email',
        ]));
    }

    /**
     * パスワードが短すぎて会員登録失敗
     */
    public function test_register_with_short_password_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->register($this->baseRegisterData([
            'password' => 'short',
        ]));
    }

    /**
     * パスワードが8文字で会員登録成功
     */
    public function test_register_with_min_password_length_success(): void
    {
        $result = $this->authService->register($this->baseRegisterData([
            'email' => 'minpass@example.com',
            'password' => '12345678',
        ]));

        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('minpass@example.com', $result['customer']->email);
    }

    /**
     * ニックネームが256文字で会員登録失敗
     */
    public function test_register_with_too_long_nickname_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->register($this->baseRegisterData([
            'email' => 'longnick@example.com',
            'nick_name' => str_repeat('a', 256),
        ]));
    }

    /**
     * 重複メールアドレスで会員登録失敗
     */
    public function test_register_with_duplicate_email_throws_validation_exception(): void
    {
        $this->createMember([
            'email' => 'duplicate@example.com',
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->register($this->baseRegisterData([
            'email' => 'duplicate@example.com',
        ]));
    }

    // ===== ゲストユーザーを会員にアップグレードテスト =====

    /**
     * ゲストユーザーの会員アップグレード成功
     */
    public function test_upgrade_to_member_success(): void
    {
        $guestCustomer = $this->createGuest();
        $result = $this->authService->upgradeToMember($guestCustomer, $this->baseUpgradeData());

        $this->assertArrayHasKey('customer', $result);
        $this->assertEquals($guestCustomer->customer_id, $result['customer']->customer_id);
        
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
        $memberCustomer = $this->createMember();

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
        $guestCustomer = $this->createGuest();

        $this->expectException(ValidationException::class);

        $this->authService->upgradeToMember($guestCustomer, $this->baseUpgradeData([
            'email' => null,
        ]));
    }

    /**
     * パスワードが8文字でゲストアップグレード成功
     */
    public function test_upgrade_to_member_with_min_password_length_success(): void
    {
        $guestCustomer = $this->createGuest();

        $result = $this->authService->upgradeToMember($guestCustomer, $this->baseUpgradeData([
            'email' => 'upgrade-minpass@example.com',
            'password' => '12345678',
        ]));

        $this->assertArrayHasKey('customer', $result);
        $this->assertFalse($result['customer']->is_guest);
    }

    /**
     * ニックネームが256文字でゲストアップグレード失敗
     */
    public function test_upgrade_to_member_with_too_long_nickname_throws_validation_exception(): void
    {
        $guestCustomer = $this->createGuest();

        $this->expectException(ValidationException::class);

        $this->authService->upgradeToMember($guestCustomer, $this->baseUpgradeData([
            'email' => 'upgrade-longnick@example.com',
            'nick_name' => str_repeat('a', 256),
        ]));
    }

    // ===== ログイン（メール/パスワード）テスト =====

    /**
     * 正常なログイン成功
     */
    public function test_login_success(): void
    {
        $password = 'password123';
        $customer = $this->createMember([
            'email' => 'login@example.com',
            'password' => bcrypt($password),
        ]);

        $result = $this->authService->login([
            'email' => 'login@example.com',
            'password' => $password,
        ]);

        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertEquals($customer->customer_id, $result['customer']->customer_id);
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
     * 無効なメール形式でログイン失敗
     */
    public function test_login_with_invalid_email_format_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->login([
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);
    }

    /**
     * 間違ったパスワードでログイン失敗
     */
    public function test_login_with_wrong_password_throws_validation_exception(): void
    {
        $this->createMember([
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
        $this->createGuest();

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
        $customer = $this->createGuest();

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
        $customer = $this->createMember([
            'email' => 'user@example.com',
            'nick_name' => 'ユーザー',
        ]);

        $result = $this->authService->getUser($customer);

        $this->assertArrayHasKey('customer', $result);
        $this->assertEquals($customer->customer_id, $result['customer']->customer_id);
    }

    // ===== ユーザー情報更新テスト =====

    /**
     * プロフィール更新成功
     */
    public function test_update_profile_success(): void
    {
        $customer = $this->createMember([
            'email' => 'update@example.com',
            'nick_name' => '古い名前',
            'password' => bcrypt('oldpassword'),
        ]);

        $result = $this->authService->updateProfile($customer, [
            'nick_name' => '新しい名前',
            'first_name' => '新太郎',
            'last_name' => '新田',
        ]);

        $this->assertArrayHasKey('customer', $result);
        
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
        $customer = $this->createMember([
            'email' => 'updatepass@example.com',
            'password' => bcrypt('oldpassword'),
        ]);

        $oldPasswordHash = $customer->password;

        $result = $this->authService->updateProfile($customer, [
            'password' => 'newpassword123',
        ]);

        $this->assertArrayHasKey('customer', $result);
        
        $customer->refresh();
        $this->assertNotEquals($oldPasswordHash, $customer->password);
    }

    /**
     * パスワードが7文字でプロフィール更新失敗
     */
    public function test_update_profile_with_short_password_throws_validation_exception(): void
    {
        $customer = $this->createMember([
            'email' => 'shortpass@example.com',
            'password' => bcrypt('oldpassword'),
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->updateProfile($customer, [
            'password' => '1234567',
        ]);
    }

    /**
     * ニックネームが255文字でプロフィール更新成功
     */
    public function test_update_profile_with_max_nickname_length_success(): void
    {
        $customer = $this->createMember([
            'email' => 'maxnick@example.com',
            'password' => bcrypt('oldpassword'),
        ]);

        $result = $this->authService->updateProfile($customer, [
            'nick_name' => str_repeat('a', 255),
        ]);

        $this->assertArrayHasKey('customer', $result);
        $this->assertEquals(255, mb_strlen($result['customer']->nick_name));
    }

    /**
     * 既存メールアドレスでプロフィール更新失敗
     */
    public function test_update_profile_with_existing_email_throws_validation_exception(): void
    {
        $this->createMember([
            'email' => 'exists@example.com',
            'password' => bcrypt('password'),
        ]);

        $customer = $this->createMember([
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
        $customer = $this->createMember([
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
        $customer = $this->createMember([
            'email' => 'self@example.com',
            'password' => bcrypt('password'),
        ]);

        $result = $this->authService->updateProfile($customer, [
            'email' => 'self@example.com',
        ]);

        $this->assertArrayHasKey('customer', $result);
        $this->assertEquals('self@example.com', $result['customer']->email);
    }
}