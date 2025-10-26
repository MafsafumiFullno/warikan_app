<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Customer;
use App\Models\ProjectTask;
use App\Models\ProjectMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    public function test_primary_key_name_is_project_id(): void
    {
        $model = new Project();
        $this->assertSame('project_id', $model->getKeyName());
    }

    public function test_mass_assignment_and_casts(): void
    {
        $model = new Project([
            'customer_id' => 123,
            'project_name' => 'テストプロジェクト',
            'description' => 'プロジェクトの説明',
            'project_status' => 'active',
            'split_method_id' => 456,
            'del_flg' => 1,
            'project_id' => 999, // 非フィルタブル属性
        ]);

        $this->assertSame(123, $model->customer_id);
        $this->assertSame('テストプロジェクト', $model->project_name);
        $this->assertSame('プロジェクトの説明', $model->description);
        $this->assertSame('active', $model->project_status);
        $this->assertSame(456, $model->split_method_id);
        $this->assertTrue($model->del_flg);

        // 非フィルタブル属性がマスアサインされなかったことを確認
        $this->assertNull($model->getAttribute('project_id'));
    }

    public function test_casts_boolean_fields(): void
    {
        $model = new Project([
            'del_flg' => '0',
        ]);

        $this->assertFalse($model->del_flg);
    }

    public function test_customer_relation_is_belongs_to(): void
    {
        $model = new Project();
        $relation = $model->customer();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_customer_relation_keys_and_related_model(): void
    {
        $model = new Project();
        $relation = $model->customer();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getOwnerKeyName());
        $this->assertSame(Customer::class, get_class($relation->getRelated()));
    }

    public function test_project_tasks_relation_is_has_many(): void
    {
        $model = new Project();
        $relation = $model->projectTasks();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_project_tasks_relation_keys_and_related_model(): void
    {
        $model = new Project();
        $relation = $model->projectTasks();

        $this->assertSame('project_id', $relation->getForeignKeyName());
        $this->assertSame('project_id', $relation->getLocalKeyName());
        $this->assertSame(ProjectTask::class, get_class($relation->getRelated()));
    }

    public function test_members_relation_is_belongs_to_many(): void
    {
        $model = new Project();
        $relation = $model->members();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_members_relation_configuration(): void
    {
        $model = new Project();
        $relation = $model->members();

        $this->assertSame('project_members', $relation->getTable());
        $this->assertSame('project_id', $relation->getForeignPivotKeyName());
        $this->assertSame('customer_id', $relation->getRelatedPivotKeyName());
        $this->assertSame(Customer::class, get_class($relation->getRelated()));
        
        // pivotカラムの確認
        $pivotColumns = $relation->getPivotColumns();
        $this->assertContains('role', $pivotColumns);
        $this->assertContains('del_flg', $pivotColumns);
    }

    public function test_project_members_relation_is_has_many(): void
    {
        $model = new Project();
        $relation = $model->projectMembers();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_project_members_relation_keys_and_related_model(): void
    {
        $model = new Project();
        $relation = $model->projectMembers();

        $this->assertSame('project_id', $relation->getForeignKeyName());
        $this->assertSame('project_id', $relation->getLocalKeyName());
        $this->assertSame(ProjectMember::class, get_class($relation->getRelated()));
    }

    public function test_table_name_is_conventional(): void
    {
        $model = new Project();
        $this->assertSame('projects', $model->getTable());
    }

    public function test_timestamps_default_true(): void
    {
        $model = new Project();
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_primary_key_type_and_incrementing(): void
    {
        $model = new Project();
        $this->assertSame('int', $model->getKeyType());
        $this->assertTrue($model->getIncrementing());
    }

    public function test_uses_model_traits(): void
    {
        $model = new Project();
        $traits = class_uses_recursive($model);
        
        $this->assertContains(\Illuminate\Database\Eloquent\Model::class, class_parents($model));
        $this->assertContains(\Illuminate\Database\Eloquent\Factories\HasFactory::class, $traits);
    }

    public function test_fillable_attributes(): void
    {
        $model = new Project();
        $fillable = $model->getFillable();
        
        $this->assertContains('customer_id', $fillable);
        $this->assertContains('project_name', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('project_status', $fillable);
        $this->assertContains('split_method_id', $fillable);
        $this->assertContains('del_flg', $fillable);
    }

    public function test_casts_configuration(): void
    {
        $model = new Project();
        $casts = $model->getCasts();
        
        $this->assertIsArray($casts);
        $this->assertArrayHasKey('del_flg', $casts);
        $this->assertSame('boolean', $casts['del_flg']);
    }

    public function test_del_flg_can_be_true(): void
    {
        $model = new Project([
            'project_name' => 'テストプロジェクト',
            'del_flg' => true,
        ]);

        $this->assertTrue($model->del_flg);
    }

    public function test_del_flg_can_be_false(): void
    {
        $model = new Project([
            'project_name' => 'テストプロジェクト',
            'del_flg' => false,
        ]);

        $this->assertFalse($model->del_flg);
    }
}
