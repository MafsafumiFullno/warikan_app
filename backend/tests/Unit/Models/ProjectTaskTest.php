<?php

namespace Tests\Unit\Models;

use App\Models\ProjectTask;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Customer;
use App\Models\ProjectTaskMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class ProjectTaskTest extends TestCase
{
    public function test_primary_key_name_is_task_id(): void
    {
        $model = new ProjectTask();
        $this->assertSame('task_id', $model->getKeyName());
    }

    public function test_mass_assignment_and_casts(): void
    {
        $model = new ProjectTask([
            'project_id' => 123,
            'project_task_code' => 'TASK-001',
            'task_name' => 'テストタスク',
            'task_member_name' => '田中太郎',
            'customer_id' => 456,
            'member_id' => 789,
            'accounting_amount' => '1500.50',
            'accounting_type' => 'expense',
            'breakdown' => '交通費',
            'payment_id' => 'PAY-001',
            'memo' => 'メモ内容',
            'del_flg' => 1,
            'task_id' => 999, // 非フィルタブル属性
        ]);

        $this->assertSame(123, $model->project_id);
        $this->assertSame('TASK-001', $model->project_task_code);
        $this->assertSame('テストタスク', $model->task_name);
        $this->assertSame('田中太郎', $model->task_member_name);
        $this->assertSame(456, $model->customer_id);
        $this->assertSame(789, $model->member_id);
        $this->assertSame('1500.50', $model->accounting_amount);
        $this->assertSame('expense', $model->accounting_type);
        $this->assertSame('交通費', $model->breakdown);
        $this->assertSame('PAY-001', $model->payment_id);
        $this->assertSame('メモ内容', $model->memo);
        $this->assertTrue($model->del_flg);

        // 非フィルタブル属性がマスアサインされなかったことを確認
        $this->assertNull($model->getAttribute('task_id'));
    }

    public function test_casts_boolean_fields(): void
    {
        $model = new ProjectTask([
            'del_flg' => '0',
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_casts_decimal_fields(): void
    {
        $model = new ProjectTask([
            'accounting_amount' => '2.3456',
        ]);

        // decimal:2キャストにより、小数点以下2桁に丸められる
        $this->assertSame('2.35', $model->accounting_amount);
    }

    public function test_casts_decimal_with_integer(): void
    {
        $model = new ProjectTask([
            'accounting_amount' => 1000,
        ]);

        $this->assertSame('1000.00', $model->accounting_amount);
    }

    public function test_casts_decimal_with_zero(): void
    {
        $model = new ProjectTask([
            'accounting_amount' => '0',
        ]);

        $this->assertSame('0.00', $model->accounting_amount);
    }

    public function test_casts_decimal_with_negative_value(): void
    {
        $model = new ProjectTask([
            'accounting_amount' => '-500.123',
        ]);

        $this->assertSame('-500.12', $model->accounting_amount);
    }

    public function test_casts_decimal_with_large_value(): void
    {
        $model = new ProjectTask([
            'accounting_amount' => '999999.999',
        ]);

        $this->assertSame('1000000.00', $model->accounting_amount);
    }

    public function test_project_relation_is_belongs_to(): void
    {
        $model = new ProjectTask();
        $relation = $model->project();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_project_relation_keys_and_related_model(): void
    {
        $model = new ProjectTask();
        $relation = $model->project();

        $this->assertSame('project_id', $relation->getForeignKeyName());
        $this->assertSame('project_id', $relation->getOwnerKeyName());
        $this->assertSame(Project::class, get_class($relation->getRelated()));
    }

    public function test_project_member_relation_is_belongs_to(): void
    {
        $model = new ProjectTask();
        $relation = $model->projectMember();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_project_member_relation_keys_and_related_model(): void
    {
        $model = new ProjectTask();
        $relation = $model->projectMember();

        $this->assertSame('member_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
        $this->assertSame(ProjectMember::class, get_class($relation->getRelated()));
    }

    public function test_customer_relation_is_belongs_to(): void
    {
        $model = new ProjectTask();
        $relation = $model->customer();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_customer_relation_keys_and_related_model(): void
    {
        $model = new ProjectTask();
        $relation = $model->customer();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getOwnerKeyName());
        $this->assertSame(Customer::class, get_class($relation->getRelated()));
    }

    public function test_task_members_relation_is_has_many(): void
    {
        $model = new ProjectTask();
        $relation = $model->taskMembers();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_task_members_relation_keys_and_related_model(): void
    {
        $model = new ProjectTask();
        $relation = $model->taskMembers();

        $this->assertSame('task_id', $relation->getForeignKeyName());
        $this->assertSame('task_id', $relation->getLocalKeyName());
        $this->assertSame(ProjectTaskMember::class, get_class($relation->getRelated()));
    }

    public function test_members_relation_is_belongs_to_many(): void
    {
        $model = new ProjectTask();
        $relation = $model->members();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_members_relation_configuration(): void
    {
        $model = new ProjectTask();
        $relation = $model->members();

        $this->assertSame('project_task_members', $relation->getTable());
        $this->assertSame('task_id', $relation->getForeignPivotKeyName());
        $this->assertSame('member_id', $relation->getRelatedPivotKeyName());
        $this->assertSame(ProjectMember::class, get_class($relation->getRelated()));
    }

    public function test_table_name_is_conventional(): void
    {
        $model = new ProjectTask();
        $this->assertSame('project_tasks', $model->getTable());
    }

    public function test_timestamps_default_true(): void
    {
        $model = new ProjectTask();
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_primary_key_type_and_incrementing(): void
    {
        $model = new ProjectTask();
        $this->assertSame('int', $model->getKeyType());
        $this->assertTrue($model->getIncrementing());
    }

    public function test_uses_model_traits(): void
    {
        $model = new ProjectTask();
        $traits = class_uses_recursive($model);
        
        $this->assertContains(\Illuminate\Database\Eloquent\Model::class, class_parents($model));
        $this->assertContains(\Illuminate\Database\Eloquent\Factories\HasFactory::class, $traits);
    }

    public function test_fillable_attributes(): void
    {
        $model = new ProjectTask();
        $fillable = $model->getFillable();
        
        $this->assertContains('project_id', $fillable);
        $this->assertContains('project_task_code', $fillable);
        $this->assertContains('task_name', $fillable);
        $this->assertContains('task_member_name', $fillable);
        $this->assertContains('customer_id', $fillable);
        $this->assertContains('member_id', $fillable);
        $this->assertContains('accounting_amount', $fillable);
        $this->assertContains('accounting_type', $fillable);
        $this->assertContains('breakdown', $fillable);
        $this->assertContains('payment_id', $fillable);
        $this->assertContains('memo', $fillable);
        $this->assertContains('del_flg', $fillable);
    }

    public function test_casts_configuration(): void
    {
        $model = new ProjectTask();
        $casts = $model->getCasts();
        
        $this->assertIsArray($casts);
        $this->assertArrayHasKey('accounting_amount', $casts);
        $this->assertArrayHasKey('del_flg', $casts);
        $this->assertSame('decimal:2', $casts['accounting_amount']);
        $this->assertSame('boolean', $casts['del_flg']);
    }

    public function test_del_flg_can_be_true(): void
    {
        $model = new ProjectTask([
            'task_name' => 'テストタスク',
            'del_flg' => true,
        ]);

        $this->assertTrue($model->del_flg);
    }

    public function test_del_flg_can_be_false(): void
    {
        $model = new ProjectTask([
            'task_name' => 'テストタスク',
            'del_flg' => false,
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_accounting_amount_with_string_zero(): void
    {
        $model = new ProjectTask([
            'accounting_amount' => '0.00',
        ]);

        $this->assertSame('0.00', $model->accounting_amount);
    }

    public function test_accounting_type_with_different_values(): void
    {
        $model = new ProjectTask([
            'accounting_type' => 'income',
        ]);

        $this->assertSame('income', $model->accounting_type);
    }

    public function test_breakdown_can_be_empty(): void
    {
        $model = new ProjectTask([
            'breakdown' => '',
        ]);

        $this->assertSame('', $model->breakdown);
    }

    public function test_memo_can_be_null(): void
    {
        $model = new ProjectTask([
            'memo' => null,
        ]);

        $this->assertNull($model->memo);
    }

    public function test_task_member_name_with_japanese(): void
    {
        $model = new ProjectTask([
            'task_member_name' => '山田花子',
        ]);

        $this->assertSame('山田花子', $model->task_member_name);
    }

    public function test_project_task_code_with_special_characters(): void
    {
        $model = new ProjectTask([
            'project_task_code' => 'TASK-2024_001',
        ]);

        $this->assertSame('TASK-2024_001', $model->project_task_code);
    }

    public function test_payment_id_with_uuid_format(): void
    {
        $model = new ProjectTask([
            'payment_id' => 'PAY-12345-67890',
        ]);

        $this->assertSame('PAY-12345-67890', $model->payment_id);
    }

    public function test_all_relations_return_correct_types(): void
    {
        $model = new ProjectTask();
        
        $this->assertInstanceOf(BelongsTo::class, $model->project());
        $this->assertInstanceOf(BelongsTo::class, $model->projectMember());
        $this->assertInstanceOf(BelongsTo::class, $model->customer());
        $this->assertInstanceOf(HasMany::class, $model->taskMembers());
        $this->assertInstanceOf(BelongsToMany::class, $model->members());
    }

    public function test_complex_task_data(): void
    {
        $model = new ProjectTask([
            'project_id' => 1,
            'project_task_code' => 'TASK-COMPLEX-001',
            'task_name' => '複雑なタスク',
            'task_member_name' => '佐藤次郎',
            'customer_id' => 100,
            'member_id' => 200,
            'accounting_amount' => '25000.75',
            'accounting_type' => 'expense',
            'breakdown' => '宿泊費・交通費・食事代',
            'payment_id' => 'PAY-COMPLEX-001',
            'memo' => '出張関連費用の精算',
            'del_flg' => false,
        ]);

        $this->assertSame(1, $model->project_id);
        $this->assertSame('TASK-COMPLEX-001', $model->project_task_code);
        $this->assertSame('複雑なタスク', $model->task_name);
        $this->assertSame('佐藤次郎', $model->task_member_name);
        $this->assertSame(100, $model->customer_id);
        $this->assertSame(200, $model->member_id);
        $this->assertSame('25000.75', $model->accounting_amount);
        $this->assertSame('expense', $model->accounting_type);
        $this->assertSame('宿泊費・交通費・食事代', $model->breakdown);
        $this->assertSame('PAY-COMPLEX-001', $model->payment_id);
        $this->assertSame('出張関連費用の精算', $model->memo);
        $this->assertFalse($model->del_flg);
    }
}
