<?php

namespace Tests\Unit\Services\Project;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use App\Models\ProjectShareLink;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMember;
use App\Services\Project\ProjectShareLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectShareLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectShareLinkService $service;
    protected ProjectRole $ownerRole;
    protected ProjectRole $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProjectShareLinkService::class);
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
            'project_name' => '共有リンクテストプロジェクト',
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

    public function test_createOrGetShareLink_creates_link_for_owner(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id);

        $result = $this->service->createOrGetShareLink($owner->customer_id, $project->project_id);

        $this->assertArrayHasKey('share_link', $result);
        $this->assertSame($project->project_id, $result['share_link']['project_id']);
        $this->assertSame('owner_only', $result['share_link']['permission']);
        $this->assertStringContainsString('/share/', $result['share_link']['share_url']);

        $savedLink = ProjectShareLink::where('project_id', $project->project_id)->first();
        $this->assertNotNull($savedLink);
        $this->assertNotEmpty($savedLink->token_hash);
        $this->assertNotEmpty($savedLink->token_encrypted);
        $this->assertSame(hash('sha256', $result['share_link']['token']), $savedLink->token_hash);
    }

    public function test_createOrGetShareLink_returns_existing_link_when_already_created(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id);

        $first = $this->service->createOrGetShareLink($owner->customer_id, $project->project_id);
        $second = $this->service->createOrGetShareLink($owner->customer_id, $project->project_id);

        $this->assertSame($first['share_link']['token'], $second['share_link']['token']);
        $this->assertSame($first['share_link']['share_url'], $second['share_link']['share_url']);
        $this->assertSame(1, ProjectShareLink::where('project_id', $project->project_id)->count());
    }

    public function test_createOrGetShareLink_fails_for_non_owner_member(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $member = $this->createCustomer(['email' => 'member@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id);
        $this->createMember($project->project_id, $member->customer_id, $this->memberRole->role_id, 2);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('オーナー権限がありません');

        $this->service->createOrGetShareLink($member->customer_id, $project->project_id);
    }

    public function test_getPublicProjectByToken_returns_project_detail_only(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);
        $project = $this->createProject($owner->customer_id, [
            'project_name' => '公開プロジェクト',
            'description' => '公開説明',
        ]);

        ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 1,
            'customer_id' => $owner->customer_id,
            'role_id' => $this->ownerRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => '一般メンバー',
        ]);
        $memberRecord = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 2,
            'customer_id' => $member->customer_id,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.50,
            'del_flg' => false,
        ]);

        $task = ProjectTask::create([
            'project_id' => $project->project_id,
            'project_task_code' => 1,
            'task_name' => 'ランチ代',
            'task_member_name' => 'オーナー',
            'accounting_amount' => 3000,
            'accounting_type' => 'expense',
            'breakdown' => null,
            'memo' => null,
            'member_id' => $memberRecord->id,
            'del_flg' => false,
        ]);
        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $memberRecord->id,
            'del_flg' => false,
        ]);

        $shareLink = $this->service->createOrGetShareLink($owner->customer_id, $project->project_id);
        $token = $shareLink['share_link']['token'];

        $result = $this->service->getPublicProjectByToken($token);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('capabilities', $result);
        $this->assertSame('公開プロジェクト', $result['project']['project_name']);
        $this->assertFalse($result['capabilities']['can_edit']);
        $this->assertArrayHasKey('accountings', $result['project']);
        $this->assertArrayHasKey('members', $result['project']);
        $this->assertArrayHasKey('split_weight', $result['project']['members'][0]);
        $this->assertArrayHasKey('total_expense', $result['project']['members'][0]);
        $this->assertArrayNotHasKey('role', $result['project']['members'][0]);
        $this->assertSame('ランチ代', $result['project']['accountings'][0]['task_name']);
        $this->assertSame(3000, $result['project']['accountings'][0]['accounting_amount']);
        $this->assertSame('expense', $result['project']['accountings'][0]['accounting_type']);
        $this->assertSame('オーナー', $result['project']['accountings'][0]['payer_name']);
        $this->assertSame(['一般メンバー'], $result['project']['accountings'][0]['target_members']);
    }

    public function test_getPublicProjectByToken_throws_not_found_for_invalid_token(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('共有リンクが見つかりません');

        $this->service->getPublicProjectByToken('invalid-token');
    }

    public function test_createOrGetShareLink_retries_when_token_hash_collides(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com']);
        $project = $this->createProject($owner->customer_id);
        $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id);

        $otherOwner = $this->createCustomer(['email' => 'other-owner@example.com']);
        $otherProject = $this->createProject($otherOwner->customer_id);
        $this->createMember($otherProject->project_id, $otherOwner->customer_id, $this->ownerRole->role_id);

        $collidingToken = str_repeat('a', 64);
        ProjectShareLink::create([
            'project_id' => $otherProject->project_id,
            'token_hash' => hash('sha256', $collidingToken),
            'token_encrypted' => Crypt::encryptString($collidingToken),
            'permission' => 'owner_only',
            'created_by_customer_id' => $otherOwner->customer_id,
            'del_flg' => false,
        ]);

        $newToken = str_repeat('b', 64);
        Str::createRandomStringsUsingSequence([$collidingToken, $newToken]);

        try {
            $result = $this->service->createOrGetShareLink($owner->customer_id, $project->project_id);
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame($newToken, $result['share_link']['token']);
        $this->assertSame(2, ProjectShareLink::count());
        $this->assertDatabaseHas('project_share_links', [
            'project_id' => $project->project_id,
            'token_hash' => hash('sha256', $newToken),
            'del_flg' => false,
        ]);
    }

    public function test_getPublicProjectByToken_uses_latest_updated_at_from_project_related_data(): void
    {
        $owner = $this->createCustomer(['email' => 'owner@example.com', 'nick_name' => 'オーナー']);
        $project = $this->createProject($owner->customer_id);
        $ownerMember = $this->createMember($project->project_id, $owner->customer_id, $this->ownerRole->role_id);

        $projectUpdatedAt = Carbon::parse('2026-05-23 10:00:00');
        $memberUpdatedAt = Carbon::parse('2026-05-23 11:00:00');
        $taskUpdatedAt = Carbon::parse('2026-05-23 12:00:00');
        $taskMemberUpdatedAt = Carbon::parse('2026-05-23 13:00:00');

        $project->timestamps = false;
        $project->forceFill(['updated_at' => $projectUpdatedAt])->save();
        $project->timestamps = true;

        $ownerMember->timestamps = false;
        $ownerMember->forceFill(['updated_at' => $memberUpdatedAt])->save();
        $ownerMember->timestamps = true;

        $task = ProjectTask::create([
            'project_id' => $project->project_id,
            'project_task_code' => 1,
            'task_name' => '会計テスト',
            'task_member_name' => 'オーナー',
            'accounting_amount' => 1000,
            'accounting_type' => 'expense',
            'breakdown' => null,
            'memo' => null,
            'member_id' => $ownerMember->id,
            'del_flg' => false,
        ]);
        $task->timestamps = false;
        $task->forceFill(['updated_at' => $taskUpdatedAt])->save();
        $task->timestamps = true;

        $taskMember = ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $ownerMember->id,
            'del_flg' => false,
        ]);
        $taskMember->timestamps = false;
        $taskMember->forceFill(['updated_at' => $taskMemberUpdatedAt])->save();
        $taskMember->timestamps = true;

        $shareLink = $this->service->createOrGetShareLink($owner->customer_id, $project->project_id);
        $result = $this->service->getPublicProjectByToken($shareLink['share_link']['token']);

        $this->assertSame(
            $taskMemberUpdatedAt->toISOString(),
            $result['project']['updated_at']->toISOString()
        );
    }
}
