<?php

namespace Tests\Unit\Services\Split;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMember;
use App\Services\Split\AdvancedSplitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AdvancedSplitServiceDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private ProjectRole $ownerRole;
    private ProjectRole $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ownerRole = ProjectRole::where('role_code', 'owner')->firstOrFail();
        $this->memberRole = ProjectRole::where('role_code', 'member')->firstOrFail();
    }

    private function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'is_guest' => false,
            'del_flg' => false,
        ], $attributes));
    }

    private function createProject(int $customerId): Project
    {
        return Project::create([
            'customer_id' => $customerId,
            'project_name' => '割り勘テスト',
            'project_status' => 'active',
            'del_flg' => false,
        ]);
    }

    private function createMember(
        int $projectId,
        int $customerId,
        int $roleId,
        int $projectMemberId,
        array $attributes = []
    ): ProjectMember {
        return ProjectMember::create(array_merge([
            'project_id' => $projectId,
            'project_member_id' => $projectMemberId,
            'customer_id' => $customerId,
            'role_id' => $roleId,
            'split_weight' => 1.00,
            'del_flg' => false,
        ], $attributes));
    }

    private function invokePrivate(string $methodName, object $instance, array $args = [])
    {
        $ref = new ReflectionMethod($instance, $methodName);
        return $ref->invokeArgs($instance, $args);
    }

    public function test_get_tasks_with_target_members_excludes_deleted_project_members(): void
    {
        $owner = $this->createCustomer(['nick_name' => 'オーナー']);
        $activeCustomer = $this->createCustomer(['nick_name' => '現役メンバー']);
        $deletedCustomer = $this->createCustomer(['nick_name' => '削除済みメンバー']);
        $project = $this->createProject($owner->customer_id);

        $ownerMember = $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id, 1);
        $activeMember = $this->createMember($project->project_id, $activeCustomer->customer_id, $this->memberRole->role_id, 2);
        $deletedMember = $this->createMember($project->project_id, $deletedCustomer->customer_id, $this->memberRole->role_id, 3, [
            'del_flg' => true,
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->project_id,
            'project_task_code' => 1,
            'task_name' => '夕食代',
            'task_member_name' => 'オーナー',
            'member_id' => $ownerMember->id,
            'accounting_amount' => 3000,
            'accounting_type' => 'expense',
            'breakdown' => null,
            'memo' => null,
            'del_flg' => false,
        ]);
        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $activeMember->id,
            'del_flg' => false,
        ]);
        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $deletedMember->id,
            'del_flg' => false,
        ]);

        $tasks = $this->invokePrivate('getTasksWithTargetMembers', new AdvancedSplitService(), [$project->project_id]);

        $this->assertCount(1, $tasks);
        $this->assertSame(['現役メンバー'], array_column($tasks[0]['target_members'], 'member_name'));
    }
}
