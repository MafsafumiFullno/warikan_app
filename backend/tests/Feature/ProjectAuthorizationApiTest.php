<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectAuthorizationApiTest extends TestCase
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
            'project_name' => '権限テストプロジェクト',
            'project_status' => 'active',
            'del_flg' => false,
        ], $attributes));
    }

    private function createMember(int $projectId, int $customerId, int $roleId, int $projectMemberId): ProjectMember
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

    public function test_project_detail_returns_owner_permission_flags(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id, 1);
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->project_id}");

        $response->assertOk()
            ->assertJsonPath('project.project_id', $project->project_id)
            ->assertJsonPath('isOwner', true)
            ->assertJsonPath('isMember', true);
    }

    public function test_project_detail_returns_member_permission_flags(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $member = $this->createCustomer(['email' => 'member@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id, 1);
        $this->createMember($project->project_id, $member->customer_id, $this->memberRole->role_id, 2);
        Sanctum::actingAs($member);

        $response = $this->getJson("/api/projects/{$project->project_id}");

        $response->assertOk()
            ->assertJsonPath('project.project_id', $project->project_id)
            ->assertJsonPath('isOwner', false)
            ->assertJsonPath('isMember', true);
    }

    public function test_project_detail_returns_403_for_existing_project_without_access(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $other = $this->createCustomer(['email' => 'other@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id, 1);
        Sanctum::actingAs($other);

        $response = $this->getJson("/api/projects/{$project->project_id}");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'アクセス権限がありません');
    }

    public function test_project_detail_returns_404_for_missing_project(): void
    {
        $customer = $this->createCustomer(['email' => 'customer@example.com']);
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/projects/999999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'プロジェクトが見つかりません');
    }

    public function test_member_cannot_update_or_delete_project(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $member = $this->createCustomer(['email' => 'member@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id, 1);
        $this->createMember($project->project_id, $member->customer_id, $this->memberRole->role_id, 2);
        Sanctum::actingAs($member);

        $this->putJson("/api/projects/{$project->project_id}", [
            'project_name' => '更新不可',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'オーナー権限がありません');

        $this->deleteJson("/api/projects/{$project->project_id}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'オーナー権限がありません');
    }

    public function test_split_calculation_uses_project_access_boundaries(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $member = $this->createCustomer(['email' => 'member@example.com']);
        $other = $this->createCustomer(['email' => 'other@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id, 1);
        $this->createMember($project->project_id, $member->customer_id, $this->memberRole->role_id, 2);

        Sanctum::actingAs($member);
        $this->postJson("/api/projects/{$project->project_id}/split-calculation")
            ->assertOk()
            ->assertJsonPath('data.project_id', $project->project_id);

        Sanctum::actingAs($other);
        $this->postJson("/api/projects/{$project->project_id}/split-calculation")
            ->assertStatus(403)
            ->assertJsonPath('message', 'アクセス権限がありません');

        Sanctum::actingAs($owner);
        $this->postJson('/api/projects/999999/split-calculation')
            ->assertStatus(404)
            ->assertJsonPath('message', 'プロジェクトが見つかりません');
    }
}
