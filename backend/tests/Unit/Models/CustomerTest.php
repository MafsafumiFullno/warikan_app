<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\OAuthAccount;
use App\Models\Project;
use App\Models\CustomerSplitMethod;
use App\Models\ProjectTask;
use App\Models\ProjectMember;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_primary_key_name_is_customer_id(): void
    {
        $model = new Customer();
        $this->assertSame('customer_id', $model->getKeyName());
    }

    public function test_mass_assignment_and_casts(): void
    {
        $model = new Customer([
            'is_guest' => 1,
            'first_name' => '太郎',
            'last_name' => '田中',
            'nick_name' => 'タロちゃん',
            'email' => 'tanaka@example.com',
            'password' => 'password123',
            'del_flg' => 1,
            'customer_id' => 999,
        ]);

        $this->assertSame('太郎', $model->first_name);
        $this->assertSame('田中', $model->last_name);
        $this->assertSame('タロちゃん', $model->nick_name);
        $this->assertSame('tanaka@example.com', $model->email);
        $this->assertSame('password123', $model->password);
        $this->assertTrue($model->is_guest);
        $this->assertTrue($model->del_flg);

        // 非フィルタブル属性がマスアサインされなかったことを確認
        $this->assertNull($model->getAttribute('customer_id'));
    }

    public function test_hidden_attributes(): void
    {
        $model = new Customer();
        $hidden = $model->getHidden();
        
        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    public function test_casts_boolean_fields(): void
    {
        $model = new Customer([
            'is_guest' => '1',
            'del_flg' => '0',
        ]);

        $this->assertTrue($model->is_guest);
        $this->assertFalse($model->del_flg);
    }


    public function test_oauth_accounts_relation_is_has_many(): void
    {
        $model = new Customer();
        $relation = $model->oauthAccounts();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_oauth_accounts_relation_keys_and_related_model(): void
    {
        $model = new Customer();
        $relation = $model->oauthAccounts();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getLocalKeyName());
        $this->assertSame(OAuthAccount::class, get_class($relation->getRelated()));
    }

    public function test_created_projects_relation_is_has_many(): void
    {
        $model = new Customer();
        $relation = $model->createdProjects();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_created_projects_relation_keys_and_related_model(): void
    {
        $model = new Customer();
        $relation = $model->createdProjects();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getLocalKeyName());
        $this->assertSame(Project::class, get_class($relation->getRelated()));
    }

    public function test_preferred_split_methods_relation_is_has_many(): void
    {
        $model = new Customer();
        $relation = $model->preferredSplitMethods();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_preferred_split_methods_relation_keys_and_related_model(): void
    {
        $model = new Customer();
        $relation = $model->preferredSplitMethods();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getLocalKeyName());
        $this->assertSame(CustomerSplitMethod::class, get_class($relation->getRelated()));
    }

    public function test_responsible_tasks_relation_is_has_many(): void
    {
        $model = new Customer();
        $relation = $model->responsibleTasks();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_responsible_tasks_relation_keys_and_related_model(): void
    {
        $model = new Customer();
        $relation = $model->responsibleTasks();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getLocalKeyName());
        $this->assertSame(ProjectTask::class, get_class($relation->getRelated()));
    }

    public function test_participating_projects_relation_is_belongs_to_many(): void
    {
        $model = new Customer();
        $relation = $model->participatingProjects();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_participating_projects_relation_configuration(): void
    {
        $model = new Customer();
        $relation = $model->participatingProjects();

        $this->assertSame('project_members', $relation->getTable());
        $this->assertSame('customer_id', $relation->getForeignPivotKeyName());
        $this->assertSame('project_id', $relation->getRelatedPivotKeyName());
        $this->assertSame(Project::class, get_class($relation->getRelated()));
        
        // pivotカラムの確認
        $pivotColumns = $relation->getPivotColumns();
        $this->assertContains('role', $pivotColumns);
        $this->assertContains('del_flg', $pivotColumns);
    }

    public function test_project_memberships_relation_is_has_many(): void
    {
        $model = new Customer();
        $relation = $model->projectMemberships();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function test_project_memberships_relation_keys_and_related_model(): void
    {
        $model = new Customer();
        $relation = $model->projectMemberships();

        $this->assertSame('customer_id', $relation->getForeignKeyName());
        $this->assertSame('customer_id', $relation->getLocalKeyName());
        $this->assertSame(ProjectMember::class, get_class($relation->getRelated()));
    }

    public function test_table_name_is_conventional(): void
    {
        $model = new Customer();
        $this->assertSame('customers', $model->getTable());
    }

    public function test_timestamps_default_true(): void
    {
        $model = new Customer();
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_primary_key_type_and_incrementing(): void
    {
        $model = new Customer();
        $this->assertSame('int', $model->getKeyType());
        $this->assertTrue($model->getIncrementing());
    }

    public function test_uses_authenticatable_traits(): void
    {
        $model = new Customer();
        $traits = class_uses_recursive($model);
        
        $this->assertContains(User::class, class_parents($model));
        $this->assertContains(HasApiTokens::class, $traits);
        $this->assertContains(HasFactory::class, $traits);
        $this->assertContains(Notifiable::class, $traits);
    }
}
