<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectShareLinkApiTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectRole $ownerRole;
    protected ProjectRole $memberRole;

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

    private function createProject(int $customerId, array $attributes = []): Project
    {
        return Project::create(array_merge([
            'customer_id' => $customerId,
            'project_name' => 'APIテストプロジェクト',
            'project_status' => 'active',
            'del_flg' => false,
        ], $attributes));
    }

    private function createMember(int $projectId, int $customerId, int $roleId, int $projectMemberId = 1): ProjectMember
    {
        return ProjectMember::create([
            'project_id' => $projectId,
            'project_member_id' => $projectMemberId,
            'customer_id' => $customerId,
            'role_id' => $roleId,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);
    }

    public function test_owner_can_create_share_link(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->project_id}/share-link");

        $response->assertOk()
            ->assertJsonPath('share_link.project_id', $project->project_id)
            ->assertJsonPath('share_link.permission', 'owner_only');
    }

    public function test_non_owner_cannot_create_share_link(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $member = $this->createCustomer(['email' => 'member@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id);
        $this->createMember($project->project_id, $member->customer_id, $this->memberRole->role_id, 2);
        Sanctum::actingAs($member);

        $response = $this->postJson("/api/projects/{$project->project_id}/share-link");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'オーナー権限がありません');
    }

    public function test_public_endpoint_returns_project_detail_by_token(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);
        $project = $this->createProject($owner->customer_id);
        $ownerMember = $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id);
        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => '一般メンバー',
        ]);
        $memberRecord = $this->createMember($project->project_id, $member->customer_id, $this->memberRole->role_id, 2);

        $task = ProjectTask::create([
            'project_id' => $project->project_id,
            'project_task_code' => 1,
            'task_name' => 'ランチ代',
            'task_member_name' => 'オーナー',
            'accounting_amount' => 3000,
            'accounting_type' => 'expense',
            'breakdown' => null,
            'memo' => null,
            'member_id' => $ownerMember->id,
            'del_flg' => false,
        ]);
        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $memberRecord->id,
            'del_flg' => false,
        ]);
        Sanctum::actingAs($owner);

        $createResponse = $this->postJson("/api/projects/{$project->project_id}/share-link");
        $token = $createResponse->json('share_link.token');

        $response = $this->getJson("/api/share/{$token}");

        $response->assertOk()
            ->assertJsonPath('project.project_id', $project->project_id)
            ->assertJsonPath('capabilities.can_edit', false)
            ->assertJsonPath('project.accountings.0.task_name', 'ランチ代')
            ->assertJsonPath('project.accountings.0.accounting_amount', 3000)
            ->assertJsonPath('project.accountings.0.accounting_type', 'expense')
            ->assertJsonPath('project.accountings.0.target_members.0', '一般メンバー')
            ->assertJsonPath('project.accountings.0.payer_name', 'オーナー')
            ->assertJsonMissingPath('project.members.0.role');
    }

    public function test_public_endpoint_returns_404_for_invalid_token(): void
    {
        $response = $this->getJson('/api/share/invalid-token');

        $response->assertStatus(404)
            ->assertJsonPath('message', '共有リンクが見つかりません');
    }
}
