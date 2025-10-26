<?php

namespace Tests\Unit\Models;

use App\Models\ProjectRole;
use App\Models\ProjectMember;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class ProjectRoleTest extends TestCase
{
    public function test_primary_key_name_is_role_id(): void
    {
        $model = new ProjectRole();
        $this->assertSame('role_id', $model->getKeyName());
    }

    public function test_mass_assignment_and_casts(): void
    {
        $model = new ProjectRole([
            'role_code' => 'ADMIN',
            'role_name' => '管理者',
            'description' => 'プロジェクト管理者の役割',
            'del_flg' => 1,
            'role_id' => 999, // 非フィルタブル属性
        ]);

        $this->assertSame('ADMIN', $model->role_code);
        $this->assertSame('管理者', $model->role_name);
        $this->assertSame('プロジェクト管理者の役割', $model->description);
        $this->assertTrue($model->del_flg);

        // 非フィルタブル属性がマスアサインされなかったことを確認
        $this->assertNull($model->getAttribute('role_id'));
    }

    public function test_casts_boolean_fields(): void
    {
        $model = new ProjectRole([
            'del_flg' => '0',
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_casts_boolean_true(): void
    {
        $model = new ProjectRole([
            'del_flg' => '1',
        ]);

        $this->assertTrue($model->del_flg);
    }

    public function test_casts_boolean_with_string_false(): void
    {
        $model = new ProjectRole([
            'del_flg' => '0',
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_casts_boolean_with_string_true(): void
    {
        $model = new ProjectRole([
            'del_flg' => '1',
        ]);

        $this->assertTrue($model->del_flg);
    }

    public function test_casts_boolean_with_empty_string(): void
    {
        $model = new ProjectRole([
            'del_flg' => '',
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_project_members_relation_is_has_many(): void
    {
        $model = new ProjectRole();
        $relation = $model->projectMembers();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_project_members_relation_keys_and_related_model(): void
    {
        $model = new ProjectRole();
        $relation = $model->projectMembers();

        $this->assertSame('role_id', $relation->getForeignKeyName());
        $this->assertSame('role_id', $relation->getLocalKeyName());
        $this->assertSame(ProjectMember::class, get_class($relation->getRelated()));
    }

    public function test_table_name_is_conventional(): void
    {
        $model = new ProjectRole();
        $this->assertSame('project_roles', $model->getTable());
    }

    public function test_timestamps_default_true(): void
    {
        $model = new ProjectRole();
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_primary_key_type_and_incrementing(): void
    {
        $model = new ProjectRole();
        $this->assertSame('int', $model->getKeyType());
        $this->assertTrue($model->getIncrementing());
    }

    public function test_uses_model_traits(): void
    {
        $model = new ProjectRole();
        $traits = class_uses_recursive($model);
        
        $this->assertContains(\Illuminate\Database\Eloquent\Model::class, class_parents($model));
        $this->assertContains(\Illuminate\Database\Eloquent\Factories\HasFactory::class, $traits);
    }

    public function test_fillable_attributes(): void
    {
        $model = new ProjectRole();
        $fillable = $model->getFillable();
        
        $this->assertContains('role_code', $fillable);
        $this->assertContains('role_name', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('del_flg', $fillable);
    }

    public function test_casts_configuration(): void
    {
        $model = new ProjectRole();
        $casts = $model->getCasts();
        
        $this->assertIsArray($casts);
        $this->assertArrayHasKey('del_flg', $casts);
        $this->assertSame('boolean', $casts['del_flg']);
    }

    public function test_del_flg_can_be_true(): void
    {
        $model = new ProjectRole([
            'role_name' => 'テストロール',
            'del_flg' => true,
        ]);

        $this->assertTrue($model->del_flg);
    }

    public function test_del_flg_can_be_false(): void
    {
        $model = new ProjectRole([
            'role_name' => 'テストロール',
            'del_flg' => false,
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_role_code_can_be_empty_string(): void
    {
        $model = new ProjectRole([
            'role_code' => '',
            'role_name' => 'テストロール',
        ]);

        $this->assertSame('', $model->role_code);
    }

    public function test_role_name_can_be_empty_string(): void
    {
        $model = new ProjectRole([
            'role_code' => 'TEST',
            'role_name' => '',
        ]);

        $this->assertSame('', $model->role_name);
    }

    public function test_description_can_be_empty_string(): void
    {
        $model = new ProjectRole([
            'role_code' => 'TEST',
            'role_name' => 'テストロール',
            'description' => '',
        ]);

        $this->assertSame('', $model->description);
    }

    public function test_description_can_be_null(): void
    {
        $model = new ProjectRole([
            'role_code' => 'TEST',
            'role_name' => 'テストロール',
            'description' => null,
        ]);

        $this->assertNull($model->description);
    }

    public function test_role_code_with_special_characters(): void
    {
        $model = new ProjectRole([
            'role_code' => 'ROLE-ADMIN_01',
            'role_name' => 'テストロール',
        ]);

        $this->assertSame('ROLE-ADMIN_01', $model->role_code);
    }

    public function test_role_name_with_japanese_characters(): void
    {
        $model = new ProjectRole([
            'role_code' => 'TEST',
            'role_name' => 'テスト管理者ロール',
        ]);

        $this->assertSame('テスト管理者ロール', $model->role_name);
    }

    public function test_description_with_multiline_text(): void
    {
        $model = new ProjectRole([
            'role_code' => 'TEST',
            'role_name' => 'テストロール',
            'description' => "複数行の\n説明文です。",
        ]);

        $this->assertSame("複数行の\n説明文です。", $model->description);
    }

    public function test_all_attributes_can_be_set_together(): void
    {
        $model = new ProjectRole([
            'role_code' => 'MANAGER',
            'role_name' => 'マネージャー',
            'description' => 'プロジェクトマネージャーの役割',
            'del_flg' => false,
        ]);

        $this->assertSame('MANAGER', $model->role_code);
        $this->assertSame('マネージャー', $model->role_name);
        $this->assertSame('プロジェクトマネージャーの役割', $model->description);
        $this->assertFalse($model->del_flg);
    }

    public function test_relation_returns_correct_type(): void
    {
        $model = new ProjectRole();
        $this->assertInstanceOf(HasMany::class, $model->projectMembers());
    }
}
