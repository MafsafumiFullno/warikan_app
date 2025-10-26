<?php

namespace Tests\Unit\Models;

use App\Models\ProjectTaskMember;
use App\Models\ProjectMember;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class ProjectTaskMemberTest extends TestCase
{
    public function test_primary_key_name_is_id(): void
    {
        $model = new ProjectTaskMember();
        $this->assertSame('id', $model->getKeyName());
    }

    public function test_mass_assignment_and_casts(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 123,
            'task_id' => 456,
            'del_flg' => 1,
            'id' => 999, // 非フィルタブル属性
        ]);

        $this->assertSame(123, $model->member_id);
        $this->assertSame(456, $model->task_id);
        $this->assertTrue($model->del_flg);

        // 非フィルタブル属性がマスアサインされなかったことを確認
        $this->assertNull($model->getAttribute('id'));
    }

    public function test_casts_boolean_fields(): void
    {
        $model = new ProjectTaskMember([
            'del_flg' => '0',
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_casts_boolean_true(): void
    {
        $model = new ProjectTaskMember([
            'del_flg' => '1',
        ]);

        $this->assertTrue($model->del_flg);
    }

    public function test_casts_boolean_with_empty_string(): void
    {
        $model = new ProjectTaskMember([
            'del_flg' => '',
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_casts_boolean_with_null(): void
    {
        $model = new ProjectTaskMember([
            'del_flg' => null,
        ]);

        $this->assertNull($model->del_flg);
    }

    public function test_project_member_relation_is_belongs_to(): void
    {
        $model = new ProjectTaskMember();
        $relation = $model->projectMember();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_project_member_relation_keys_and_related_model(): void
    {
        $model = new ProjectTaskMember();
        $relation = $model->projectMember();

        $this->assertSame('member_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame(ProjectMember::class, get_class($relation->getRelated()));
    }

    public function test_project_task_relation_is_belongs_to(): void
    {
        $model = new ProjectTaskMember();
        $relation = $model->projectTask();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_project_task_relation_keys_and_related_model(): void
    {
        $model = new ProjectTaskMember();
        $relation = $model->projectTask();

        $this->assertSame('task_id', $relation->getForeignKeyName());
        $this->assertSame('task_id', $relation->getOwnerKeyName());
        $this->assertSame(ProjectTask::class, get_class($relation->getRelated()));
    }

    public function test_table_name_is_conventional(): void
    {
        $model = new ProjectTaskMember();
        $this->assertSame('project_task_members', $model->getTable());
    }

    public function test_timestamps_default_true(): void
    {
        $model = new ProjectTaskMember();
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_primary_key_type_and_incrementing(): void
    {
        $model = new ProjectTaskMember();
        $this->assertSame('int', $model->getKeyType());
        $this->assertTrue($model->getIncrementing());
    }

    public function test_uses_model_traits(): void
    {
        $model = new ProjectTaskMember();
        $traits = class_uses_recursive($model);
        
        $this->assertContains(\Illuminate\Database\Eloquent\Model::class, class_parents($model));
        $this->assertContains(\Illuminate\Database\Eloquent\Factories\HasFactory::class, $traits);
    }

    public function test_fillable_attributes(): void
    {
        $model = new ProjectTaskMember();
        $fillable = $model->getFillable();
        
        $this->assertContains('member_id', $fillable);
        $this->assertContains('task_id', $fillable);
        $this->assertContains('del_flg', $fillable);
    }

    public function test_casts_configuration(): void
    {
        $model = new ProjectTaskMember();
        $casts = $model->getCasts();
        
        $this->assertIsArray($casts);
        $this->assertArrayHasKey('del_flg', $casts);
        $this->assertSame('boolean', $casts['del_flg']);
    }

    public function test_del_flg_can_be_true(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 1,
            'task_id' => 1,
            'del_flg' => true,
        ]);

        $this->assertTrue($model->del_flg);
    }

    public function test_del_flg_can_be_false(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 1,
            'task_id' => 1,
            'del_flg' => false,
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_member_id_can_be_zero(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 0,
            'task_id' => 1,
        ]);

        $this->assertSame(0, $model->member_id);
    }

    public function test_task_id_can_be_zero(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 1,
            'task_id' => 0,
        ]);

        $this->assertSame(0, $model->task_id);
    }

    public function test_member_id_can_be_negative(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => -1,
            'task_id' => 1,
        ]);

        $this->assertSame(-1, $model->member_id);
    }

    public function test_task_id_can_be_negative(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 1,
            'task_id' => -1,
        ]);

        $this->assertSame(-1, $model->task_id);
    }

    public function test_member_id_can_be_large_number(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 999999,
            'task_id' => 1,
        ]);

        $this->assertSame(999999, $model->member_id);
    }

    public function test_task_id_can_be_large_number(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 1,
            'task_id' => 999999,
        ]);

        $this->assertSame(999999, $model->task_id);
    }

    public function test_all_attributes_can_be_set_together(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 100,
            'task_id' => 200,
            'del_flg' => false,
        ]);

        $this->assertSame(100, $model->member_id);
        $this->assertSame(200, $model->task_id);
        $this->assertFalse($model->del_flg);
    }

    public function test_all_relations_return_correct_types(): void
    {
        $model = new ProjectTaskMember();
        
        $this->assertInstanceOf(BelongsTo::class, $model->projectMember());
        $this->assertInstanceOf(BelongsTo::class, $model->projectTask());
    }

    public function test_relation_foreign_keys_are_correct(): void
    {
        $model = new ProjectTaskMember();
        
        $projectMemberRelation = $model->projectMember();
        $projectTaskRelation = $model->projectTask();
        
        $this->assertSame('member_id', $projectMemberRelation->getForeignKeyName());
        $this->assertSame('task_id', $projectTaskRelation->getForeignKeyName());
    }

    public function test_relation_owner_keys_are_correct(): void
    {
        $model = new ProjectTaskMember();
        
        $projectMemberRelation = $model->projectMember();
        $projectTaskRelation = $model->projectTask();
        
        $this->assertSame('id', $projectMemberRelation->getOwnerKeyName());
        $this->assertSame('task_id', $projectTaskRelation->getOwnerKeyName());
    }

    public function test_model_can_be_created_with_minimal_data(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 1,
            'task_id' => 1,
        ]);

        $this->assertSame(1, $model->member_id);
        $this->assertSame(1, $model->task_id);
        $this->assertNull($model->del_flg); // デフォルト値（null）
    }

    public function test_model_can_be_created_with_all_data(): void
    {
        $model = new ProjectTaskMember([
            'member_id' => 50,
            'task_id' => 75,
            'del_flg' => true,
        ]);

        $this->assertSame(50, $model->member_id);
        $this->assertSame(75, $model->task_id);
        $this->assertTrue($model->del_flg);
    }

    public function test_boolean_casts_with_various_inputs(): void
    {
        // テストケース: 様々な入力値でのブール値キャスト
        $testCases = [
            ['input' => 0, 'expected' => false],
            ['input' => 1, 'expected' => true],
            ['input' => '0', 'expected' => false],
            ['input' => '1', 'expected' => true],
            ['input' => false, 'expected' => false],
            ['input' => true, 'expected' => true],
            ['input' => null, 'expected' => null],
        ];

        foreach ($testCases as $case) {
            $model = new ProjectTaskMember([
                'member_id' => 1,
                'task_id' => 1,
                'del_flg' => $case['input'],
            ]);

            $this->assertSame($case['expected'], $model->del_flg, 
                "Failed for input: " . var_export($case['input'], true));
        }
    }
}
