<?php

namespace Tests\Unit\Models;

use App\Models\ProjectMember;
use App\Models\Project;
use App\Models\Customer;
use App\Models\ProjectRole;
use App\Models\ProjectTaskMember;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    public function test_primary_key_name_is_id(): void
    {
        $model = new ProjectMember();
        $this->assertSame('id', $model->getKeyName());
    }

    public function test_mass_assignment_and_casts(): void
    {
        $model = new ProjectMember([
            'project_id' => 123,
            'project_member_id' => 456,
            'customer_id' => 789,
            'member_name' => '田中太郎',
            'member_email' => 'tanaka@example.com',
            'memo' => 'メモ内容',
            'role_id' => 1,
            'split_weight' => '1.50',
            'del_flg' => 1,
            'id' => 999, // 非フィルタブル属性
        ]);

        $this->assertSame(123, $model->project_id);
        $this->assertSame(456, $model->project_member_id);
        $this->assertSame(789, $model->customer_id);
        $this->assertSame('田中太郎', $model->member_name);
        $this->assertSame('tanaka@example.com', $model->member_email);
        $this->assertSame('メモ内容', $model->memo);
        $this->assertSame(1, $model->role_id);
        $this->assertSame('1.50', $model->split_weight);
        $this->assertTrue($model->del_flg);

        // 非フィルタブル属性がマスアサインされなかったことを確認
        $this->assertNull($model->getAttribute('id'));
    }

    public function test_casts_boolean_fields(): void
    {
        $model = new ProjectMember([
            'del_flg' => '0',
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_casts_decimal_fields(): void
    {
        $model = new ProjectMember([
            'split_weight' => '2.3456',
        ]);

        // decimal:2キャストにより、小数点以下2桁に丸められる
        $this->assertSame('2.35', $model->split_weight);
    }

    public function test_casts_decimal_with_integer(): void
    {
        $model = new ProjectMember([
            'split_weight' => 5,
        ]);

        $this->assertSame('5.00', $model->split_weight);
    }

    public function test_casts_decimal_with_zero(): void
    {
        $model = new ProjectMember([
            'split_weight' => '0',
        ]);

        $this->assertSame('0.00', $model->split_weight);
    }

    public function test_project_relation_is_belongs_to(): void
    {
        $model = new ProjectMember();
        $relation = $model->project();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_project_relation_keys_and_related_model(): void
    {
        $model = new ProjectMember();
        $relation = $model->project();

        $this->assertSame('project_id', $relation->getForeignKeyName());
        $this->assertSame('project_id', $relation->getOwnerKeyName());
        $this->assertSame(Project::class, get_class($relation->getRelated()));
    }

    public function test_customer_relation_is_belongs_to(): void
    {
        $model = new ProjectMember();
        $relation = $model->customer();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_customer_relation_keys_and_related_model(): void
    {
        $model = new ProjectMember();
        $relation = $model->customer();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getOwnerKeyName());
        $this->assertSame(Customer::class, get_class($relation->getRelated()));
    }

    public function test_role_relation_is_belongs_to(): void
    {
        $model = new ProjectMember();
        $relation = $model->role();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_role_relation_keys_and_related_model(): void
    {
        $model = new ProjectMember();
        $relation = $model->role();

        $this->assertSame('role_id', $relation->getForeignKeyName());
        $this->assertSame('role_id', $relation->getOwnerKeyName());
        $this->assertSame(ProjectRole::class, get_class($relation->getRelated()));
    }

    public function test_task_members_relation_is_has_many(): void
    {
        $model = new ProjectMember();
        $relation = $model->taskMembers();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_task_members_relation_keys_and_related_model(): void
    {
        $model = new ProjectMember();
        $relation = $model->taskMembers();

        $this->assertSame('member_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getLocalKeyName());
        $this->assertSame(ProjectTaskMember::class, get_class($relation->getRelated()));
    }

    public function test_tasks_relation_is_belongs_to_many(): void
    {
        $model = new ProjectMember();
        $relation = $model->tasks();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_tasks_relation_configuration(): void
    {
        $model = new ProjectMember();
        $relation = $model->tasks();

        $this->assertSame('project_task_members', $relation->getTable());
        $this->assertSame('member_id', $relation->getForeignPivotKeyName());
        $this->assertSame('task_id', $relation->getRelatedPivotKeyName());
        $this->assertSame(ProjectTask::class, get_class($relation->getRelated()));
    }

    public function test_table_name_is_conventional(): void
    {
        $model = new ProjectMember();
        $this->assertSame('project_members', $model->getTable());
    }

    public function test_timestamps_default_true(): void
    {
        $model = new ProjectMember();
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_primary_key_type_and_incrementing(): void
    {
        $model = new ProjectMember();
        $this->assertSame('int', $model->getKeyType());
        $this->assertTrue($model->getIncrementing());
    }

    public function test_uses_model_traits(): void
    {
        $model = new ProjectMember();
        $traits = class_uses_recursive($model);
        
        $this->assertContains(\Illuminate\Database\Eloquent\Model::class, class_parents($model));
        $this->assertContains(\Illuminate\Database\Eloquent\Factories\HasFactory::class, $traits);
    }

    public function test_fillable_attributes(): void
    {
        $model = new ProjectMember();
        $fillable = $model->getFillable();
        
        $this->assertContains('project_id', $fillable);
        $this->assertContains('project_member_id', $fillable);
        $this->assertContains('customer_id', $fillable);
        $this->assertContains('member_name', $fillable);
        $this->assertContains('member_email', $fillable);
        $this->assertContains('memo', $fillable);
        $this->assertContains('role_id', $fillable);
        $this->assertContains('split_weight', $fillable);
        $this->assertContains('del_flg', $fillable);
    }

    public function test_casts_configuration(): void
    {
        $model = new ProjectMember();
        $casts = $model->getCasts();
        
        $this->assertIsArray($casts);
        $this->assertArrayHasKey('del_flg', $casts);
        $this->assertArrayHasKey('split_weight', $casts);
        $this->assertSame('boolean', $casts['del_flg']);
        $this->assertSame('decimal:2', $casts['split_weight']);
    }

    public function test_del_flg_can_be_true(): void
    {
        $model = new ProjectMember([
            'member_name' => 'テストメンバー',
            'del_flg' => true,
        ]);

        $this->assertTrue($model->del_flg);
    }

    public function test_del_flg_can_be_false(): void
    {
        $model = new ProjectMember([
            'member_name' => 'テストメンバー',
            'del_flg' => false,
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_split_weight_with_negative_value(): void
    {
        $model = new ProjectMember([
            'split_weight' => '-1.234',
        ]);

        $this->assertSame('-1.23', $model->split_weight);
    }

    public function test_split_weight_with_large_decimal(): void
    {
        $model = new ProjectMember([
            'split_weight' => '999.999',
        ]);

        $this->assertSame('1000.00', $model->split_weight);
    }

    public function test_split_weight_with_string_zero(): void
    {
        $model = new ProjectMember([
            'split_weight' => '0.00',
        ]);

        $this->assertSame('0.00', $model->split_weight);
    }

    public function test_all_relations_return_correct_types(): void
    {
        $model = new ProjectMember();
        
        $this->assertInstanceOf(BelongsTo::class, $model->project());
        $this->assertInstanceOf(BelongsTo::class, $model->customer());
        $this->assertInstanceOf(BelongsTo::class, $model->role());
        $this->assertInstanceOf(HasMany::class, $model->taskMembers());
        $this->assertInstanceOf(BelongsToMany::class, $model->tasks());
    }
}
