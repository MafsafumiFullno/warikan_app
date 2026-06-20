<?php

namespace Tests\Unit\Services\Project;

use App\Services\Project\ProjectTaskService;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectMember;
use App\Models\ProjectTaskMember;
use App\Models\Customer;
use App\Models\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectTaskServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectTaskService $projectTaskService;
    protected ProjectRole $ownerRole;
    protected ProjectRole $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectTaskService = app(ProjectTaskService::class);

        // マイグレーションで作成されたロールを取得
        $this->ownerRole = ProjectRole::where('role_code', 'owner')->firstOrFail();
        $this->memberRole = ProjectRole::where('role_code', 'member')->firstOrFail();
    }

    /**
     * テスト用の共通Customerデータを作成する
     */
    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'is_guest' => false,
            'del_flg' => false,
        ], $attributes));
    }

    /**
     * テスト用の共通Projectデータを作成する
     */
    protected function createProject(int $customerId, array $attributes = []): Project
    {
        return Project::create(array_merge([
            'customer_id' => $customerId,
            'project_name' => 'テストプロジェクト',
            'project_status' => 'active',
            'del_flg' => false,
        ], $attributes));
    }

    /**
     * テスト用の共通ProjectMemberデータを作成する
     */
    protected function createProjectMember(int $projectId, array $attributes = []): ProjectMember
    {
        return ProjectMember::create(array_merge([
            'project_id' => $projectId,
            'project_member_id' => 1,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ], $attributes));
    }

    /**
     * テスト用の共通ProjectTaskデータを作成する
     */
    protected function createProjectTask(int $projectId, array $attributes = []): ProjectTask
    {
        $task = ProjectTask::create(array_merge([
            'project_id' => $projectId,
            'project_task_code' => 1,
            'task_name' => 'テスト会計',
            'task_member_name' => 'テストメンバー',
            'accounting_amount' => 1000.00,
            'accounting_type' => 'expense',
            'del_flg' => false,
        ], $attributes));

        // created_at/updated_at をテストで指定したい場合のみ、タイムスタンプ自動更新を無効化して反映
        if (array_key_exists('created_at', $attributes) || array_key_exists('updated_at', $attributes)) {
            $task->timestamps = false;
            $task->forceFill([
                'created_at' => $attributes['created_at'] ?? $task->created_at,
                'updated_at' => $attributes['updated_at'] ?? $task->updated_at,
            ])->save();
            $task->timestamps = true;
        }

        return $task;
    }

    // ===== プロジェクトの会計一覧を取得テスト =====

    /**
     * 正常にプロジェクトの会計一覧を取得できること
     */
    public function test_getProjectTasks_success(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $task1 = $this->createProjectTask($project->project_id, [
            'task_name' => '会計1',
            'created_at' => now()->subDays(2),
        ]);

        $task2 = $this->createProjectTask($project->project_id, [
            'task_name' => '会計2',
            'created_at' => now()->subDays(1),
        ]);

        $result = $this->projectTaskService->getProjectTasks($customer->customer_id, $project->project_id);

        $this->assertArrayHasKey('accountings', $result);
        $this->assertCount(2, $result['accountings']);
        // 作成日時の降順で並んでいることを確認
        $this->assertEquals($task2->task_id, $result['accountings'][0]['task_id']);
        $this->assertEquals($task1->task_id, $result['accountings'][1]['task_id']);
    }

    /**
     * 削除された会計が除外されること
     */
    public function test_getProjectTasks_excludes_deleted_tasks(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $task1 = $this->createProjectTask($project->project_id, [
            'task_name' => '会計1',
        ]);

        $task2 = $this->createProjectTask($project->project_id, [
            'task_name' => '会計2',
            'del_flg' => true, // 削除済み
        ]);

        $result = $this->projectTaskService->getProjectTasks($customer->customer_id, $project->project_id);

        $this->assertCount(1, $result['accountings']);
        $this->assertEquals($task1->task_id, $result['accountings'][0]['task_id']);
    }

    /**
     * 対象メンバーが正しく取得されること
     */
    public function test_getProjectTasks_includes_target_members(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member1 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $member2 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 2,
            'member_name' => 'メンバー2',
        ]);

        $task = $this->createProjectTask($project->project_id);

        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member1->id,
            'del_flg' => false,
        ]);

        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member2->id,
            'del_flg' => false,
        ]);

        $result = $this->projectTaskService->getProjectTasks($customer->customer_id, $project->project_id);

        $this->assertCount(1, $result['accountings']);
        $accounting = $result['accountings'][0];
        $this->assertArrayHasKey('target_members', $accounting);
        $this->assertArrayHasKey('target_member_ids', $accounting);
        $this->assertCount(2, $accounting['target_members']);
        $this->assertCount(2, $accounting['target_member_ids']);
        $this->assertEqualsCanonicalizing([1, 2], $accounting['target_member_ids']);
    }

    /**
     * 削除された対象メンバーが除外されること
     */
    public function test_getProjectTasks_excludes_deleted_target_members(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member1 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $member2 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 2,
            'member_name' => 'メンバー2',
        ]);

        $task = $this->createProjectTask($project->project_id);

        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member1->id,
            'del_flg' => false,
        ]);

        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member2->id,
            'del_flg' => true, // 削除済み
        ]);

        $result = $this->projectTaskService->getProjectTasks($customer->customer_id, $project->project_id);

        $this->assertCount(1, $result['accountings']);
        $accounting = $result['accountings'][0];
        $this->assertCount(1, $accounting['target_members']);
    }

    /**
     * 削除されたプロジェクトメンバーが対象メンバー履歴から除外されること
     */
    public function test_getProjectTasks_excludes_deleted_project_members_from_target_members(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member1 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $member2 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 2,
            'member_name' => 'メンバー2',
            'del_flg' => true,
        ]);

        $task = $this->createProjectTask($project->project_id);

        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member1->id,
            'del_flg' => false,
        ]);

        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member2->id,
            'del_flg' => false,
        ]);

        $result = $this->projectTaskService->getProjectTasks($customer->customer_id, $project->project_id);

        $this->assertCount(1, $result['accountings']);
        $accounting = $result['accountings'][0];
        $this->assertSame(['メンバー1'], array_values($accounting['target_members']));
        $this->assertSame([$member1->id], array_values($accounting['target_member_ids']));
    }

    /**
     * プロジェクトが存在しない場合にエラーが発生すること
     */
    public function test_getProjectTasks_fails_when_project_not_found(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません');

        $this->projectTaskService->getProjectTasks($customer->customer_id, 999);
    }

    /**
     * アクセス権限がない場合にエラーが発生すること
     */
    public function test_getProjectTasks_fails_when_no_access_permission(): void
    {
        $owner = $this->createCustomer();
        $unauthorizedCustomer = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('アクセス権限がありません');

        $this->projectTaskService->getProjectTasks($unauthorizedCustomer->customer_id, $project->project_id);
    }

    /**
     * 会計が0件の場合に空の配列が返されること
     */
    public function test_getProjectTasks_returns_empty_when_no_tasks(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $result = $this->projectTaskService->getProjectTasks($customer->customer_id, $project->project_id);

        $this->assertCount(0, $result['accountings']);
    }

    /**
     * メンバーとしてアクセスできること
     */
    public function test_getProjectTasks_success_as_member(): void
    {
        $owner = $this->createCustomer();
        $member = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);

        // メンバーを追加
        ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 2,
            'customer_id' => $member->customer_id,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        $task = $this->createProjectTask($project->project_id);

        $result = $this->projectTaskService->getProjectTasks($member->customer_id, $project->project_id);

        $this->assertArrayHasKey('accountings', $result);
        $this->assertCount(1, $result['accountings']);
        $this->assertEquals($task->task_id, $result['accountings'][0]['task_id']);
    }

    // ===== 会計を追加テスト =====

    /**
     * 正常に会計を追加できること
     */
    public function test_createProjectTask_success(): void
    {
        $customer = $this->createCustomer([
            'first_name' => '太郎',
            'last_name' => '山田',
        ]);
        $project = $this->createProject($customer->customer_id);
        $ownerMember = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'customer_id' => $customer->customer_id,
            'role_id' => $this->ownerRole->role_id,
        ]);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000.50,
            'description' => 'テスト用の会計',
            'accounting_type' => 'expense',
            'member_name' => '太郎 山田',
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $this->assertArrayHasKey('accounting', $result);
        $this->assertEquals('新規会計', $result['accounting']['task_name']);
        $this->assertEquals(5000, $result['accounting']['accounting_amount']); // 整数に変換される
        $this->assertEquals('テスト用の会計', $result['accounting']['breakdown']);
        $this->assertEquals('expense', $result['accounting']['accounting_type']);
        $this->assertEquals('太郎 山田', $result['accounting']['task_member_name']);

        // データベースに保存されていることを確認
        $task = ProjectTask::where('task_id', $result['accounting']['task_id'])->first();
        $this->assertNotNull($task);
        $this->assertEquals(1, $task->project_task_code); // 最初のタスクコード
        $this->assertEquals($ownerMember->id, $task->member_id);
    }

    /**
     * 対象メンバーを指定して会計を追加できること
     */
    public function test_createProjectTask_with_target_members(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member1 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $member2 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 2,
            'member_name' => 'メンバー2',
        ]);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'メンバー1',
            'target_member_ids' => [$member1->project_member_id, $member2->project_member_id],
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $this->assertArrayHasKey('accounting', $result);
        $this->assertCount(2, $result['accounting']['target_members']);
        $this->assertCount(2, $result['accounting']['target_member_ids']);

        // データベースに保存されていることを確認
        $taskMembers = ProjectTaskMember::where('task_id', $result['accounting']['task_id'])
            ->where('del_flg', false)
            ->get();
        $this->assertCount(2, $taskMembers);
    }

    /**
     * プロジェクトメンバーが支払人の場合に正しく設定されること
     */
    public function test_createProjectTask_with_member_as_payer(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'テストメンバー',
        ]);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $task = ProjectTask::where('task_id', $result['accounting']['task_id'])->first();
        $this->assertEquals($member->id, $task->member_id);
    }

    /**
     * 同名メンバーでもmember_idで支払人を設定できること
     */
    public function test_createProjectTask_uses_member_id_for_payer_reference(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => '同名メンバー',
        ]);

        $payer = $this->createProjectMember($project->project_id, [
            'project_member_id' => 2,
            'member_name' => '同名メンバー',
        ]);

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_id' => $payer->id,
        ]);

        $task = ProjectTask::where('task_id', $result['accounting']['task_id'])->first();
        $this->assertEquals($payer->id, $task->member_id);
        $this->assertEquals('同名メンバー', $task->task_member_name);
    }

    /**
     * オーナーが支払人の場合に正しく設定されること
     */
    public function test_createProjectTask_with_owner_as_payer(): void
    {
        $customer = $this->createCustomer([
            'first_name' => '太郎',
            'last_name' => '山田',
        ]);
        $project = $this->createProject($customer->customer_id);
        $ownerMember = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'customer_id' => $customer->customer_id,
            'role_id' => $this->ownerRole->role_id,
        ]);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => '太郎 山田',
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $task = ProjectTask::where('task_id', $result['accounting']['task_id'])->first();
        $this->assertEquals($ownerMember->id, $task->member_id);
    }

    /**
     * ゲストが支払人の場合に正しく設定されること
     */
    public function test_createProjectTask_with_guest_as_payer(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'ゲストユーザー',
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $task = ProjectTask::where('task_id', $result['accounting']['task_id'])->first();
        $this->assertNull($task->member_id); // ゲストの場合はmember_idがnull
    }

    /**
     * タスクコードが正しく生成されること
     */
    public function test_createProjectTask_generates_correct_task_code(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        // 既存のタスクを作成
        $this->createProjectTask($project->project_id, [
            'project_task_code' => 5,
        ]);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $task = ProjectTask::where('task_id', $result['accounting']['task_id'])->first();
        $this->assertEquals(6, $task->project_task_code); // 次のタスクコード
    }

    /**
     * 会計名が未指定の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_accounting_name_missing(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * 金額が未指定の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_amount_missing(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * 金額が0未満の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_amount_negative(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => -100,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * 金額が0の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_amount_zero(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => 0,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * メンバー名が未指定の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_member_name_missing(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => 5000,
        ]);
    }

    /**
     * 無効な対象メンバーIDでエラーが発生すること
     */
    public function test_createProjectTask_fails_with_invalid_target_member_id(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('対象メンバーが見つかりません');

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'target_member_ids' => [999], // 存在しない project_member_id
        ]);
    }

    /**
     * オーナー権限がない場合にエラーが発生すること
     */
    public function test_createProjectTask_fails_when_not_owner(): void
    {
        $owner = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('オーナー権限がありません');

        $this->projectTaskService->createProjectTask($otherCustomer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * デフォルトのaccounting_typeが設定されること
     */
    public function test_createProjectTask_with_default_accounting_type(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            // accounting_typeを指定しない
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $this->assertEquals('expense', $result['accounting']['accounting_type']);
    }

    /**
     * descriptionがnullで会計を作成できること
     */
    public function test_createProjectTask_with_null_description(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'description' => null,
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $this->assertNull($result['accounting']['breakdown']);
    }

    /**
     * 会計名が最大長（255文字）で作成できること
     */
    public function test_createProjectTask_with_max_length_accounting_name(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $maxLengthName = str_repeat('a', 255);

        $data = [
            'accounting_name' => $maxLengthName,
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $this->assertEquals($maxLengthName, $result['accounting']['task_name']);
    }

    /**
     * 会計名が256文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_accounting_name_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $tooLongName = str_repeat('a', 256);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => $tooLongName,
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * descriptionが最大長（1000文字）で作成できること
     */
    public function test_createProjectTask_with_max_length_description(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $maxLengthDescription = str_repeat('a', 1000);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'description' => $maxLengthDescription,
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $this->assertEquals($maxLengthDescription, $result['accounting']['breakdown']);
    }

    /**
     * descriptionが1001文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_description_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $tooLongDescription = str_repeat('a', 1001);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'description' => $tooLongDescription,
        ]);
    }

    /**
     * accounting_typeが最大長（50文字）で作成できること
     */
    public function test_createProjectTask_with_max_length_accounting_type(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $maxLengthType = str_repeat('a', 50);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'accounting_type' => $maxLengthType,
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $this->assertEquals($maxLengthType, $result['accounting']['accounting_type']);
    }

    /**
     * accounting_typeが51文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_accounting_type_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $tooLongType = str_repeat('a', 51);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'accounting_type' => $tooLongType,
        ]);
    }

    /**
     * member_nameが最大長（255文字）で作成できること
     */
    public function test_createProjectTask_with_max_length_member_name(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $maxLengthName = str_repeat('a', 255);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => $maxLengthName,
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        $this->assertEquals($maxLengthName, $result['accounting']['task_member_name']);
    }

    /**
     * member_nameが256文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_createProjectTask_fails_when_member_name_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $tooLongName = str_repeat('a', 256);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => $tooLongName,
        ]);
    }

    /**
     * 対象メンバーIDの重複が除去されること
     */
    public function test_createProjectTask_with_duplicate_target_member_ids(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'target_member_ids' => [$member->project_member_id, $member->project_member_id, $member->project_member_id], // 重複
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        // 重複が除去されて1つだけになる
        $taskMembers = ProjectTaskMember::where('task_id', $result['accounting']['task_id'])
            ->where('del_flg', false)
            ->get();
        $this->assertCount(1, $taskMembers);
    }

    /**
     * 論理削除済みの対象メンバーを追加する場合に復活すること
     */
    public function test_createProjectTask_restores_deleted_target_member(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        // 既存のタスクと論理削除済みの対象メンバーを作成
        $existingTask = $this->createProjectTask($project->project_id);
        ProjectTaskMember::create([
            'task_id' => $existingTask->task_id,
            'member_id' => $member->id,
            'del_flg' => true, // 論理削除済み
        ]);

        $data = [
            'accounting_name' => '新規会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'target_member_ids' => [$member->project_member_id],
        ];

        $result = $this->projectTaskService->createProjectTask($customer->customer_id, $project->project_id, $data);

        // 新しいタスクに追加される
        $taskMembers = ProjectTaskMember::where('task_id', $result['accounting']['task_id'])
            ->where('member_id', $member->id)
            ->where('del_flg', false)
            ->get();
        $this->assertCount(1, $taskMembers);
    }

    // ===== 会計を更新テスト =====

    /**
     * 正常に会計を更新できること
     */
    public function test_updateProjectTask_success(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $task = $this->createProjectTask($project->project_id, [
            'task_name' => '元の会計名',
            'accounting_amount' => 1000,
        ]);

        $data = [
            'accounting_name' => '更新された会計名',
            'amount' => 2000,
            'description' => '更新された説明',
            'accounting_type' => 'income',
            'member_name' => '更新されたメンバー',
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        $this->assertArrayHasKey('accounting', $result);
        $this->assertEquals('更新された会計名', $result['accounting']['task_name']);
        $this->assertEquals(2000, $result['accounting']['accounting_amount']);
        $this->assertEquals('更新された説明', $result['accounting']['breakdown']);
        $this->assertEquals('income', $result['accounting']['accounting_type']);
        $this->assertEquals('更新されたメンバー', $result['accounting']['task_member_name']);
    }

    /**
     * 会計更新時にmember_idで支払人参照を更新できること
     */
    public function test_updateProjectTask_updates_payer_member_id(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $originalPayer = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => '支払人',
        ]);

        $newPayer = $this->createProjectMember($project->project_id, [
            'project_member_id' => 2,
            'member_name' => '支払人',
        ]);

        $task = $this->createProjectTask($project->project_id, [
            'member_id' => $originalPayer->id,
            'task_member_name' => '支払人',
        ]);

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_id' => $newPayer->id,
        ]);

        $task->refresh();
        $this->assertEquals($newPayer->id, $task->member_id);
        $this->assertEquals($newPayer->id, $result['accounting']['member_id']);
    }

    /**
     * 対象メンバーを更新できること
     */
    public function test_updateProjectTask_with_target_members(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member1 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $member2 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 2,
            'member_name' => 'メンバー2',
        ]);

        $member3 = $this->createProjectMember($project->project_id, [
            'project_member_id' => 3,
            'member_name' => 'メンバー3',
        ]);

        $task = $this->createProjectTask($project->project_id);

        // 初期の対象メンバーを設定
        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member1->id,
            'del_flg' => false,
        ]);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'target_member_ids' => [$member2->project_member_id, $member3->project_member_id], // メンバーを変更
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        $this->assertCount(2, $result['accounting']['target_members']);
        $this->assertContains($member2->project_member_id, $result['accounting']['target_member_ids']);
        $this->assertContains($member3->project_member_id, $result['accounting']['target_member_ids']);
        $this->assertNotContains($member1->project_member_id, $result['accounting']['target_member_ids']);

        // データベースを確認
        $taskMembers = ProjectTaskMember::where('task_id', $task->task_id)
            ->where('del_flg', false)
            ->get();
        $this->assertCount(2, $taskMembers);
    }

    /**
     * 対象メンバーを空にできること
     */
    public function test_updateProjectTask_with_empty_target_members(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $task = $this->createProjectTask($project->project_id);

        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member->id,
            'del_flg' => false,
        ]);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'target_member_ids' => [], // 空の配列
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        $this->assertCount(0, $result['accounting']['target_members']);

        // データベースを確認
        $taskMembers = ProjectTaskMember::where('task_id', $task->task_id)
            ->where('del_flg', false)
            ->get();
        $this->assertCount(0, $taskMembers);
    }

    /**
     * 会計名が未指定の場合にバリデーションエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_accounting_name_missing(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * 金額が未指定の場合にバリデーションエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_amount_missing(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => '更新された会計',
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * 会計が存在しない場合にエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_task_not_found(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('会計が見つかりません');

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, 999, [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * オーナー権限がない場合にエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_not_owner(): void
    {
        $owner = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('オーナー権限がありません');

        $this->projectTaskService->updateProjectTask($otherCustomer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * 論理削除済みの対象メンバーを復活できること
     */
    public function test_updateProjectTask_restores_deleted_target_member(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $task = $this->createProjectTask($project->project_id);

        // 論理削除済みの対象メンバーを作成
        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member->id,
            'del_flg' => true,
        ]);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'target_member_ids' => [$member->project_member_id], // 復活させる
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        $this->assertCount(1, $result['accounting']['target_members']);

        // データベースを確認
        $taskMember = ProjectTaskMember::where('task_id', $task->task_id)
            ->where('member_id', $member->id)
            ->where('del_flg', false)
            ->first();
        $this->assertNotNull($taskMember);
    }

    /**
     * target_member_idsがnullの場合に更新されないこと
     */
    public function test_updateProjectTask_with_null_target_member_ids(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $task = $this->createProjectTask($project->project_id);

        // 初期の対象メンバーを設定
        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member->id,
            'del_flg' => false,
        ]);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            // target_member_idsを指定しない（null）
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        // 既存の対象メンバーが残っていることを確認
        $this->assertCount(1, $result['accounting']['target_members']);

        // データベースを確認
        $taskMembers = ProjectTaskMember::where('task_id', $task->task_id)
            ->where('del_flg', false)
            ->get();
        $this->assertCount(1, $taskMembers);
    }

    /**
     * 金額が0未満の場合にバリデーションエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_amount_negative(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => '更新された会計',
            'amount' => -100,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * 金額が0の場合にバリデーションエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_amount_zero(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => '更新された会計',
            'amount' => 0,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * 重複した対象メンバーIDが除去されること
     */
    public function test_updateProjectTask_with_duplicate_target_member_ids(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $task = $this->createProjectTask($project->project_id);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'target_member_ids' => [$member->project_member_id, $member->project_member_id, $member->project_member_id], // 重複
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        // 重複が除去されて1つだけになる
        $this->assertCount(1, $result['accounting']['target_members']);

        // データベースを確認
        $taskMembers = ProjectTaskMember::where('task_id', $task->task_id)
            ->where('del_flg', false)
            ->get();
        $this->assertCount(1, $taskMembers);
    }

    /**
     * 同じmember_idで複数のレコードがある場合に最新の1つだけが残ること
     */
    public function test_updateProjectTask_with_multiple_records_same_member(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $member = $this->createProjectMember($project->project_id, [
            'project_member_id' => 1,
            'member_name' => 'メンバー1',
        ]);

        $task = $this->createProjectTask($project->project_id);

        // 同じmember_idで複数のレコードを作成
        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member->id,
            'del_flg' => false,
        ]);

        ProjectTaskMember::create([
            'task_id' => $task->task_id,
            'member_id' => $member->id,
            'del_flg' => false,
        ]);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'target_member_ids' => [$member->project_member_id],
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        // 最新の1つだけが残る
        $taskMembers = ProjectTaskMember::where('task_id', $task->task_id)
            ->where('member_id', $member->id)
            ->where('del_flg', false)
            ->get();
        $this->assertCount(1, $taskMembers);
    }

    /**
     * 会計名が最大長（255文字）で更新できること
     */
    public function test_updateProjectTask_with_max_length_accounting_name(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $maxLengthName = str_repeat('a', 255);

        $data = [
            'accounting_name' => $maxLengthName,
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        $this->assertEquals($maxLengthName, $result['accounting']['task_name']);
    }

    /**
     * 会計名が256文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_accounting_name_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $tooLongName = str_repeat('a', 256);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => $tooLongName,
            'amount' => 5000,
            'member_name' => 'テストメンバー',
        ]);
    }

    /**
     * descriptionが最大長（1000文字）で更新できること
     */
    public function test_updateProjectTask_with_max_length_description(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $maxLengthDescription = str_repeat('a', 1000);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'description' => $maxLengthDescription,
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        $this->assertEquals($maxLengthDescription, $result['accounting']['breakdown']);
    }

    /**
     * descriptionが1001文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_description_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $tooLongDescription = str_repeat('a', 1001);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'description' => $tooLongDescription,
        ]);
    }

    /**
     * accounting_typeが最大長（50文字）で更新できること
     */
    public function test_updateProjectTask_with_max_length_accounting_type(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $maxLengthType = str_repeat('a', 50);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'accounting_type' => $maxLengthType,
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        $this->assertEquals($maxLengthType, $result['accounting']['accounting_type']);
    }

    /**
     * accounting_typeが51文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_accounting_type_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $tooLongType = str_repeat('a', 51);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => 'テストメンバー',
            'accounting_type' => $tooLongType,
        ]);
    }

    /**
     * member_nameが最大長（255文字）で更新できること
     */
    public function test_updateProjectTask_with_max_length_member_name(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $maxLengthName = str_repeat('a', 255);

        $data = [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => $maxLengthName,
        ];

        $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, $data);

        $this->assertEquals($maxLengthName, $result['accounting']['task_member_name']);
    }

    /**
     * member_nameが256文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_updateProjectTask_fails_when_member_name_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $tooLongName = str_repeat('a', 256);

        $this->expectException(ValidationException::class);

        $this->projectTaskService->updateProjectTask($customer->customer_id, $project->project_id, $task->task_id, [
            'accounting_name' => '更新された会計',
            'amount' => 5000,
            'member_name' => $tooLongName,
        ]);
    }

    // ===== 会計を削除テスト =====

    /**
     * 正常に会計を削除できること
     */
    public function test_deleteProjectTask_success(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $result = $this->projectTaskService->deleteProjectTask($customer->customer_id, $project->project_id, $task->task_id);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('会計を削除しました', $result['message']);

        // 論理削除されていることを確認
        $task->refresh();
        $this->assertTrue($task->del_flg);
    }

    /**
     * 会計が存在しない場合にエラーが発生すること
     */
    public function test_deleteProjectTask_fails_when_task_not_found(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('会計が見つかりません');

        $this->projectTaskService->deleteProjectTask($customer->customer_id, $project->project_id, 999);
    }

    /**
     * オーナー権限がない場合にエラーが発生すること
     */
    public function test_deleteProjectTask_fails_when_not_owner(): void
    {
        $owner = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);
        $task = $this->createProjectTask($project->project_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('オーナー権限がありません');

        $this->projectTaskService->deleteProjectTask($otherCustomer->customer_id, $project->project_id, $task->task_id);
    }

    /**
     * 既に削除された会計を削除しようとした場合にエラーが発生すること
     */
    public function test_deleteProjectTask_fails_when_already_deleted(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $task = $this->createProjectTask($project->project_id, [
            'del_flg' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('会計が見つかりません');

        $this->projectTaskService->deleteProjectTask($customer->customer_id, $project->project_id, $task->task_id);
    }
}

