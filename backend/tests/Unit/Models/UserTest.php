<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_primary_key_name_is_id(): void
    {
        $model = new User();
        $this->assertSame('id', $model->getKeyName());
    }

    public function test_mass_assignment_and_casts(): void
    {
        $model = new User([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com',
            'password' => 'password123',
            'id' => 999, // 非フィルタブル属性
        ]);

        $this->assertSame('田中太郎', $model->name);
        $this->assertSame('tanaka@example.com', $model->email);
        $this->assertNotSame('password123', $model->password);
        $this->assertIsString($model->password);
        $this->assertGreaterThan(50, strlen($model->password));

        // 非フィルタブル属性がマスアサインされなかったことを確認
        $this->assertNull($model->getAttribute('id'));
    }

    public function test_hidden_attributes(): void
    {
        $model = new User();
        $hidden = $model->getHidden();
        
        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    public function test_casts_email_verified_at_as_datetime(): void
    {
        $model = new User([
            'email_verified_at' => '2023-01-01 12:00:00',
        ]);

        $this->assertInstanceOf(\DateTimeInterface::class, $model->email_verified_at);
    }

    public function test_casts_password_as_hashed(): void
    {
        $model = new User([
            'password' => 'plaintext_password',
        ]);

        // hashedキャストにより、パスワードがハッシュ化されることを確認
        $this->assertNotSame('plaintext_password', $model->password);
        $this->assertIsString($model->password);
        $this->assertGreaterThan(50, strlen($model->password)); // ハッシュは長い文字列
    }

    public function test_table_name_is_conventional(): void
    {
        $model = new User();
        $this->assertSame('users', $model->getTable());
    }

    public function test_timestamps_default_true(): void
    {
        $model = new User();
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_primary_key_type_and_incrementing(): void
    {
        $model = new User();
        $this->assertSame('int', $model->getKeyType());
        $this->assertTrue($model->getIncrementing());
    }

    public function test_uses_authenticatable_traits(): void
    {
        $model = new User();
        $traits = class_uses_recursive($model);
        
        $this->assertContains(Authenticatable::class, class_parents($model));
        $this->assertContains(HasFactory::class, $traits);
        $this->assertContains(Notifiable::class, $traits);
    }

    public function test_fillable_attributes(): void
    {
        $model = new User();
        $fillable = $model->getFillable();
        
        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
    }

    public function test_casts_method_returns_array(): void
    {
        $model = new User();
        $casts = $model->getCasts();
        
        $this->assertIsArray($casts);
        $this->assertArrayHasKey('email_verified_at', $casts);
        $this->assertArrayHasKey('password', $casts);
        $this->assertSame('datetime', $casts['email_verified_at']);
        $this->assertSame('hashed', $casts['password']);
    }

    public function test_email_verified_at_can_be_null(): void
    {
        $model = new User([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertNull($model->email_verified_at);
    }

    public function test_password_is_automatically_hashed_on_assignment(): void
    {
        $model = new User();
        $model->password = 'test_password';
        
        $this->assertNotSame('test_password', $model->password);
        $this->assertIsString($model->password);
        $this->assertGreaterThan(50, strlen($model->password));
    }
}
