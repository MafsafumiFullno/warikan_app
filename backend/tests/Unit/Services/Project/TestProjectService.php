<?php

namespace Tests\Unit\Services\Project;

use App\Services\Project\ProjectService;
use App\Models\Project;
use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use App\Models\CustomerSplitMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestProjectService extends TestCase
{
    use RefreshDatabase;

    protected ProjectService $projectService;
    protected ProjectRole $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectService = app(ProjectService::class);

        // マイグレーションで作成されたロールを取得
        $this->ownerRole = ProjectRole::where('role_code', 'owner')->firstOrFail();
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
        $project = Project::create(array_merge([
            'customer_id' => $customerId,
            'project_name' => 'テストプロジェクト',
            'project_status' => 'active',
            'del_flg' => false,
        ], $attributes));

        // created_at/updated_at をテストで指定したい場合のみ、タイムスタンプ自動更新を無効化して反映
        if (array_key_exists('created_at', $attributes) || array_key_exists('updated_at', $attributes)) {
            $project->timestamps = false;
            $project->forceFill([
                'created_at' => $attributes['created_at'] ?? $project->created_at,
                'updated_at' => $attributes['updated_at'] ?? $project->updated_at,
            ])->save();
            $project->timestamps = true;
        }
        return $project;
    }

    /**
     * テスト用の共通ProjectMemberデータを作成する
     */
    protected function createProjectMember(int $projectId, array $attributes = []): ProjectMember
    {
        return ProjectMember::create(array_merge([
            'project_id' => $projectId,
            'project_member_id' => 1,
            'customer_id' => $attributes['customer_id'] ?? null,
            'role_id' => $this->ownerRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ], $attributes));
    }

    /**
     * テスト用の共通CustomerSplitMethodデータを作成する
     */
    protected function createCustomerSplitMethod(int $customerId, array $attributes = []): CustomerSplitMethod
    {
        return CustomerSplitMethod::create(array_merge([
            'customer_id' => $customerId,
            'description' => 'テスト割り勘方法',
            'template_type' => 'equal',
            'del_flg' => false,
        ], $attributes));
    }

    // ===== プロジェクト一覧を取得テスト =====

    /**
     * 正常にプロジェクト一覧を取得できること
     */
    public function test_getProjectsForCustomer_success(): void
    {
        $customer = $this->createCustomer([
            'email' => 'test@example.com',
            'nick_name' => 'テストユーザー',
        ]);

        $project1 = $this->createProject($customer->customer_id, [
            'project_name' => 'プロジェクト1',
            'created_at' => now()->subDays(2),
        ]);

        $project2 = $this->createProject($customer->customer_id, [
            'project_name' => 'プロジェクト2',
            'created_at' => now()->subDays(1),
        ]);

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id);

        $this->assertArrayHasKey('projects', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(2, $result['projects']);
        $this->assertEquals(2, $result['pagination']['total']);
        // 作成日時の降順で並んでいることを確認
        $this->assertEquals($project2->project_id, $result['projects'][0]->project_id);
        $this->assertEquals($project1->project_id, $result['projects'][1]->project_id);
    }

    /**
     * 削除されたプロジェクトが除外されること
     */
    public function test_getProjectsForCustomer_excludes_deleted_projects(): void
    {
        $customer = $this->createCustomer();

        $project1 = $this->createProject($customer->customer_id, [
            'project_name' => 'プロジェクト1',
        ]);

        $project2 = $this->createProject($customer->customer_id, [
            'project_name' => 'プロジェクト2',
            'del_flg' => true, // 削除済み
        ]);

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id);

        $this->assertCount(1, $result['projects']);
        $this->assertEquals($project1->project_id, $result['projects'][0]->project_id);
    }

    /**
     * ステータスフィルターが正常に動作すること
     */
    public function test_getProjectsForCustomer_with_status_filter(): void
    {
        $customer = $this->createCustomer();

        $project1 = $this->createProject($customer->customer_id, [
            'project_name' => 'アクティブプロジェクト',
            'project_status' => 'active',
        ]);

        $project2 = $this->createProject($customer->customer_id, [
            'project_name' => 'ドラフトプロジェクト',
            'project_status' => 'draft',
        ]);

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id, [
            'project_status' => 'active',
        ]);

        $this->assertCount(1, $result['projects']);
        $this->assertEquals($project1->project_id, $result['projects'][0]->project_id);
        $this->assertEquals('active', $result['projects'][0]->project_status);
    }

    /**
     * キーワード検索が正常に動作すること（プロジェクト名）
     */
    public function test_getProjectsForCustomer_with_keyword_search_by_name(): void
    {
        $customer = $this->createCustomer();

        $project1 = $this->createProject($customer->customer_id, [
            'project_name' => 'テストプロジェクト',
        ]);

        $project2 = $this->createProject($customer->customer_id, [
            'project_name' => 'サンプルプロジェクト',
        ]);

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id, [
            'q' => 'テスト',
        ]);

        $this->assertCount(1, $result['projects']);
        $this->assertEquals($project1->project_id, $result['projects'][0]->project_id);
    }

    /**
     * キーワード検索が正常に動作すること（説明）
     */
    public function test_getProjectsForCustomer_with_keyword_search_by_description(): void
    {
        $customer = $this->createCustomer();

        $project1 = $this->createProject($customer->customer_id, [
            'project_name' => 'プロジェクト1',
            'description' => 'これはテスト用のプロジェクトです',
        ]);

        $project2 = $this->createProject($customer->customer_id, [
            'project_name' => 'プロジェクト2',
            'description' => 'これはサンプルです',
        ]);

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id, [
            'q' => 'テスト',
        ]);

        $this->assertCount(1, $result['projects']);
        $this->assertEquals($project1->project_id, $result['projects'][0]->project_id);
    }

    /**
     * ページネーションが正常に動作すること
     */
    public function test_getProjectsForCustomer_with_pagination(): void
    {
        $customer = $this->createCustomer();

        // 25件のプロジェクトを作成
        for ($i = 1; $i <= 25; $i++) {
            $this->createProject($customer->customer_id, [
                'project_name' => "プロジェクト{$i}",
            ]);
        }

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id, [
            'per_page' => 10,
        ]);

        $this->assertCount(10, $result['projects']);
        $this->assertEquals(25, $result['pagination']['total']);
        $this->assertEquals(10, $result['pagination']['per_page']);
        $this->assertEquals(3, $result['pagination']['last_page']);
    }

    /**
     * ページネーションの全フィールドが正しく返されること
     */
    public function test_getProjectsForCustomer_pagination_fields(): void
    {
        $customer = $this->createCustomer();

        // 15件のプロジェクトを作成
        for ($i = 1; $i <= 15; $i++) {
            $this->createProject($customer->customer_id, [
                'project_name' => "プロジェクト{$i}",
            ]);
        }

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id, [
            'per_page' => 10,
        ]);

        $pagination = $result['pagination'];
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('last_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('from', $pagination);
        $this->assertArrayHasKey('to', $pagination);
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(15, $pagination['total']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(2, $pagination['last_page']);
        $this->assertEquals(1, $pagination['from']);
        $this->assertEquals(10, $pagination['to']);
    }

    /**
     * 他の顧客のプロジェクトが含まれないこと
     */
    public function test_getProjectsForCustomer_excludes_other_customer_projects(): void
    {
        $customer1 = $this->createCustomer();
        $customer2 = $this->createCustomer();

        $project1 = $this->createProject($customer1->customer_id, [
            'project_name' => '顧客1のプロジェクト',
        ]);

        $project2 = $this->createProject($customer2->customer_id, [
            'project_name' => '顧客2のプロジェクト',
        ]);

        $result = $this->projectService->getProjectsForCustomer($customer1->customer_id);

        $this->assertCount(1, $result['projects']);
        $this->assertEquals($project1->project_id, $result['projects'][0]->project_id);
        $this->assertNotEquals($project2->project_id, $result['projects'][0]->project_id);
    }

    /**
     * プロジェクトが0件の場合に空の配列が返されること
     */
    public function test_getProjectsForCustomer_returns_empty_when_no_projects(): void
    {
        $customer = $this->createCustomer();

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id);

        $this->assertCount(0, $result['projects']);
        $this->assertEquals(0, $result['pagination']['total']);
    }

    /**
     * 複合フィルター（ステータス + キーワード）が正常に動作すること
     */
    public function test_getProjectsForCustomer_with_combined_filters(): void
    {
        $customer = $this->createCustomer();

        $project1 = $this->createProject($customer->customer_id, [
            'project_name' => 'アクティブテストプロジェクト',
            'project_status' => 'active',
        ]);

        $project2 = $this->createProject($customer->customer_id, [
            'project_name' => 'ドラフトテストプロジェクト',
            'project_status' => 'draft',
        ]);

        $project3 = $this->createProject($customer->customer_id, [
            'project_name' => 'アクティブサンプルプロジェクト',
            'project_status' => 'active',
        ]);

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id, [
            'project_status' => 'active',
            'q' => 'テスト',
        ]);

        $this->assertCount(1, $result['projects']);
        $this->assertEquals($project1->project_id, $result['projects'][0]->project_id);
    }

    /**
     * 空のキーワード検索が正常に動作すること（全件取得）
     */
    public function test_getProjectsForCustomer_with_empty_keyword(): void
    {
        $customer = $this->createCustomer();

        $project1 = $this->createProject($customer->customer_id, [
            'project_name' => 'プロジェクト1',
        ]);

        $project2 = $this->createProject($customer->customer_id, [
            'project_name' => 'プロジェクト2',
        ]);

        $result = $this->projectService->getProjectsForCustomer($customer->customer_id, [
            'q' => '',
        ]);

        // 空文字列の場合は全件取得される
        $this->assertCount(2, $result['projects']);
    }

    // ===== プロジェクト作成テスト =====

    /**
     * 正常にプロジェクトを作成できること
     */
    public function test_createProject_success(): void
    {
        $customer = $this->createCustomer();

        $data = [
            'project_name' => '新規プロジェクト',
            'description' => 'これはテスト用のプロジェクトです',
            'project_status' => 'active',
        ];

        $result = $this->projectService->createProject($customer->customer_id, $data);

        $this->assertArrayHasKey('project', $result);
        $this->assertEquals('新規プロジェクト', $result['project']->project_name);
        $this->assertEquals('これはテスト用のプロジェクトです', $result['project']->description);
        $this->assertEquals('active', $result['project']->project_status);
        $this->assertEquals($customer->customer_id, $result['project']->customer_id);
        $this->assertFalse($result['project']->del_flg);

        // オーナーがメンバーとして追加されていることを確認
        $ownerMember = ProjectMember::where('project_id', $result['project']->project_id)
            ->where('customer_id', $customer->customer_id)
            ->where('project_member_id', 1)
            ->first();

        $this->assertNotNull($ownerMember);
        $this->assertEquals($this->ownerRole->role_id, $ownerMember->role_id);
        $this->assertEquals(1.00, $ownerMember->split_weight);
    }

    /**
     * デフォルトステータスでプロジェクトを作成できること
     */
    public function test_createProject_with_default_status(): void
    {
        $customer = $this->createCustomer();

        $data = [
            'project_name' => '新規プロジェクト',
        ];

        $result = $this->projectService->createProject($customer->customer_id, $data);

        $this->assertEquals('draft', $result['project']->project_status);
    }

    /**
     * 割り勘方法IDを指定してプロジェクトを作成できること
     */
    public function test_createProject_with_split_method_id(): void
    {
        $customer = $this->createCustomer();

        $splitMethod = $this->createCustomerSplitMethod($customer->customer_id);

        $data = [
            'project_name' => '新規プロジェクト',
            'split_method_id' => $splitMethod->split_method_id,
        ];

        $result = $this->projectService->createProject($customer->customer_id, $data);

        $this->assertEquals($splitMethod->split_method_id, $result['project']->split_method_id);
    }

    /**
     * プロジェクト名が未指定の場合にバリデーションエラーが発生すること
     */
    public function test_createProject_fails_when_project_name_missing(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(ValidationException::class);

        $this->projectService->createProject($customer->customer_id, []);
    }

    /**
     * 無効なステータスでバリデーションエラーが発生すること
     */
    public function test_createProject_fails_with_invalid_status(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(ValidationException::class);

        $this->projectService->createProject($customer->customer_id, [
            'project_name' => 'テストプロジェクト',
            'project_status' => 'invalid_status',
        ]);
    }

    /**
     * 無効な割り勘方法IDでエラーが発生すること
     */
    public function test_createProject_fails_with_invalid_split_method_id(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('無効な割り勘方法IDです。');

        $this->projectService->createProject($customer->customer_id, [
            'project_name' => 'テストプロジェクト',
            'split_method_id' => 999,
        ]);
    }

    /**
     * 他の顧客の割り勘方法IDでエラーが発生すること
     */
    public function test_createProject_fails_with_other_customer_split_method_id(): void
    {
        $customer1 = $this->createCustomer();
        $customer2 = $this->createCustomer();

        $splitMethod = $this->createCustomerSplitMethod($customer2->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('無効な割り勘方法IDです。');

        $this->projectService->createProject($customer1->customer_id, [
            'project_name' => 'テストプロジェクト',
            'split_method_id' => $splitMethod->split_method_id,
        ]);
    }

    /**
     * split_method_idが0の場合にエラーが発生すること
     */
    public function test_createProject_fails_with_zero_split_method_id(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('無効な割り勘方法IDです。');

        $this->projectService->createProject($customer->customer_id, [
            'project_name' => 'テストプロジェクト',
            'split_method_id' => 0,
        ]);
    }

    /**
     * プロジェクト名が最大長（255文字）で作成できること
     */
    public function test_createProject_with_max_length_project_name(): void
    {
        $customer = $this->createCustomer();

        $maxLengthName = str_repeat('a', 255);

        $data = [
            'project_name' => $maxLengthName,
        ];

        $result = $this->projectService->createProject($customer->customer_id, $data);

        $this->assertEquals($maxLengthName, $result['project']->project_name);
    }

    /**
     * プロジェクト名が256文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_createProject_fails_when_project_name_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();

        $tooLongName = str_repeat('a', 256);

        $this->expectException(ValidationException::class);

        $this->projectService->createProject($customer->customer_id, [
            'project_name' => $tooLongName,
        ]);
    }

    /**
     * 説明がnullでプロジェクトを作成できること
     */
    public function test_createProject_with_null_description(): void
    {
        $customer = $this->createCustomer();

        $data = [
            'project_name' => '新規プロジェクト',
            'description' => null,
        ];

        $result = $this->projectService->createProject($customer->customer_id, $data);

        $this->assertNull($result['project']->description);
    }

    /**
     * 説明が空文字列でプロジェクトを作成できること
     */
    public function test_createProject_with_empty_description(): void
    {
        $customer = $this->createCustomer();

        $data = [
            'project_name' => '新規プロジェクト',
            'description' => '',
        ];

        $result = $this->projectService->createProject($customer->customer_id, $data);

        $this->assertEquals('', $result['project']->description);
    }

    /**
     * すべてのステータスでプロジェクトを作成できること
     */
    public function test_createProject_with_all_statuses(): void
    {
        $customer = $this->createCustomer();

        $statuses = ['draft', 'active', 'completed', 'archived'];

        foreach ($statuses as $status) {
            $data = [
                'project_name' => "{$status}プロジェクト",
                'project_status' => $status,
            ];

            $result = $this->projectService->createProject($customer->customer_id, $data);

            $this->assertEquals($status, $result['project']->project_status);
        }
    }

    // ===== プロジェクト取得（アクセス権限チェック付き）テスト =====

    /**
     * オーナーとしてプロジェクトを取得できること
     */
    public function test_getProjectWithAccessCheck_success_as_owner(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);
        $this->createProjectMember($project->project_id, [
            'customer_id' => $customer->customer_id,
        ]);

        $result = $this->projectService->getProjectWithAccessCheck($customer->customer_id, $project->project_id);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('isOwner', $result);
        $this->assertArrayHasKey('isMember', $result);
        $this->assertEquals($project->project_id, $result['project']->project_id);
        $this->assertTrue($result['isOwner']);
        $this->assertTrue($result['isMember']); // オーナーもメンバーとして追加されている
    }

    /**
     * メンバーとしてプロジェクトを取得できること
     */
    public function test_getProjectWithAccessCheck_success_as_member(): void
    {
        $owner = $this->createCustomer();
        $member = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);

        // メンバーを追加
        ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 2,
            'customer_id' => $member->customer_id,
            'role_id' => ProjectRole::where('role_code', 'member')->firstOrFail()->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        $result = $this->projectService->getProjectWithAccessCheck($member->customer_id, $project->project_id);

        $this->assertEquals($project->project_id, $result['project']->project_id);
        $this->assertFalse($result['isOwner']);
        $this->assertTrue($result['isMember']);
    }

    /**
     * プロジェクトが存在しない場合にエラーが発生すること
     */
    public function test_getProjectWithAccessCheck_fails_when_project_not_found(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません');

        $this->projectService->getProjectWithAccessCheck($customer->customer_id, 999);
    }

    /**
     * アクセス権限がない場合にエラーが発生すること
     */
    public function test_getProjectWithAccessCheck_fails_when_no_access_permission(): void
    {
        $owner = $this->createCustomer();
        $unauthorizedCustomer = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('アクセス権限がありません');

        $this->projectService->getProjectWithAccessCheck($unauthorizedCustomer->customer_id, $project->project_id);
    }

    /**
     * 削除されたプロジェクトの場合にエラーが発生すること
     */
    public function test_getProjectWithAccessCheck_fails_when_project_deleted(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id, [
            'del_flg' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません');

        $this->projectService->getProjectWithAccessCheck($customer->customer_id, $project->project_id);
    }

    /**
     * 削除されたメンバーはアクセス権限がないこと
     */
    public function test_getProjectWithAccessCheck_excludes_deleted_member(): void
    {
        $owner = $this->createCustomer();
        $member = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);

        // メンバーを追加してから削除
        $projectMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 2,
            'customer_id' => $member->customer_id,
            'role_id' => ProjectRole::where('role_code', 'member')->firstOrFail()->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        // メンバーを削除
        $projectMember->update(['del_flg' => true]);

        // 削除されたメンバーはアクセス権限がないためエラーが発生する
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('アクセス権限がありません');

        $this->projectService->getProjectWithAccessCheck($member->customer_id, $project->project_id);
    }

    // ===== プロジェクト更新テスト =====

    /**
     * 正常にプロジェクトを更新できること
     */
    public function test_updateProject_success(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id, [
            'project_name' => '元のプロジェクト名',
            'description' => '元の説明',
            'project_status' => 'draft',
        ]);

        $data = [
            'project_name' => '更新されたプロジェクト名',
            'description' => '更新された説明',
            'project_status' => 'active',
        ];

        $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, $data);

        $this->assertArrayHasKey('project', $result);
        $this->assertEquals('更新されたプロジェクト名', $result['project']->project_name);
        $this->assertEquals('更新された説明', $result['project']->description);
        $this->assertEquals('active', $result['project']->project_status);
    }

    /**
     * 部分的な更新ができること
     */
    public function test_updateProject_with_partial_update(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id, [
            'project_name' => '元のプロジェクト名',
            'description' => '元の説明',
            'project_status' => 'draft',
        ]);

        $data = [
            'project_name' => '更新されたプロジェクト名',
        ];

        $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, $data);

        $this->assertEquals('更新されたプロジェクト名', $result['project']->project_name);
        $this->assertEquals('元の説明', $result['project']->description); // 変更されていない
        $this->assertEquals('draft', $result['project']->project_status); // 変更されていない
    }

    /**
     * 割り勘方法IDを更新できること
     */
    public function test_updateProject_with_split_method_id(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $splitMethod = $this->createCustomerSplitMethod($customer->customer_id);

        $data = [
            'split_method_id' => $splitMethod->split_method_id,
        ];

        $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, $data);

        $this->assertEquals($splitMethod->split_method_id, $result['project']->split_method_id);
    }

    /**
     * プロジェクトが存在しない場合にエラーが発生すること
     */
    public function test_updateProject_fails_when_project_not_found(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません。');

        $this->projectService->updateProject($customer->customer_id, 999, [
            'project_name' => 'テストプロジェクト',
        ]);
    }

    /**
     * オーナーでない場合にエラーが発生すること
     */
    public function test_updateProject_fails_when_not_owner(): void
    {
        $owner = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません。');

        $this->projectService->updateProject($otherCustomer->customer_id, $project->project_id, [
            'project_name' => 'テストプロジェクト',
        ]);
    }

    /**
     * 無効なステータスでバリデーションエラーが発生すること
     */
    public function test_updateProject_fails_with_invalid_status(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(ValidationException::class);

        $this->projectService->updateProject($customer->customer_id, $project->project_id, [
            'project_status' => 'invalid_status',
        ]);
    }

    /**
     * 無効な割り勘方法IDでエラーが発生すること
     */
    public function test_updateProject_fails_with_invalid_split_method_id(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('無効な割り勘方法IDです。');

        $this->projectService->updateProject($customer->customer_id, $project->project_id, [
            'split_method_id' => 999,
        ]);
    }

    /**
     * 割り勘方法IDをnullに設定して削除できること
     */
    public function test_updateProject_can_remove_split_method_id(): void
    {
        $customer = $this->createCustomer();
        $splitMethod = $this->createCustomerSplitMethod($customer->customer_id);

        $project = $this->createProject($customer->customer_id, [
            'split_method_id' => $splitMethod->split_method_id,
        ]);

        $data = [
            'split_method_id' => null,
        ];

        $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, $data);

        $this->assertNull($result['project']->split_method_id);
    }

    /**
     * プロジェクト名が最大長（255文字）で更新できること
     */
    public function test_updateProject_with_max_length_project_name(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $maxLengthName = str_repeat('a', 255);

        $data = [
            'project_name' => $maxLengthName,
        ];

        $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, $data);

        $this->assertEquals($maxLengthName, $result['project']->project_name);
    }

    /**
     * プロジェクト名が256文字以上の場合にバリデーションエラーが発生すること
     */
    public function test_updateProject_fails_when_project_name_exceeds_max_length(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $tooLongName = str_repeat('a', 256);

        $this->expectException(ValidationException::class);

        $this->projectService->updateProject($customer->customer_id, $project->project_id, [
            'project_name' => $tooLongName,
        ]);
    }

    /**
     * すべてのステータスでプロジェクトを更新できること
     */
    public function test_updateProject_with_all_statuses(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $statuses = ['draft', 'active', 'completed', 'archived'];

        foreach ($statuses as $status) {
            $data = [
                'project_status' => $status,
            ];

            $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, $data);

            $this->assertEquals($status, $result['project']->project_status);
        }
    }

    /**
     * 他の顧客の割り勘方法IDで更新しようとした場合にエラーが発生すること
     */
    public function test_updateProject_fails_with_other_customer_split_method_id(): void
    {
        $customer1 = $this->createCustomer();
        $customer2 = $this->createCustomer();

        $project = $this->createProject($customer1->customer_id);
        $splitMethod = $this->createCustomerSplitMethod($customer2->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('無効な割り勘方法IDです。');

        $this->projectService->updateProject($customer1->customer_id, $project->project_id, [
            'split_method_id' => $splitMethod->split_method_id,
        ]);
    }

    /**
     * 空のデータ配列で更新しようとした場合に何も更新されないこと
     */
    public function test_updateProject_with_empty_data(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id, [
            'project_name' => '元のプロジェクト名',
            'description' => '元の説明',
            'project_status' => 'draft',
        ]);

        $originalName = $project->project_name;
        $originalDescription = $project->description;
        $originalStatus = $project->project_status;

        $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, []);

        $this->assertEquals($originalName, $result['project']->project_name);
        $this->assertEquals($originalDescription, $result['project']->description);
        $this->assertEquals($originalStatus, $result['project']->project_status);
    }

    /**
     * descriptionを空文字列に更新できること
     */
    public function test_updateProject_with_empty_description(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id, [
            'project_name' => 'テストプロジェクト',
            'description' => '元の説明',
        ]);

        $data = [
            'description' => '',
        ];

        $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, $data);

        $this->assertEquals('', $result['project']->description);
    }

    /**
     * descriptionをnullに更新できること
     */
    public function test_updateProject_with_null_description(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id, [
            'project_name' => 'テストプロジェクト',
            'description' => '元の説明',
        ]);

        $data = [
            'description' => null,
        ];

        $result = $this->projectService->updateProject($customer->customer_id, $project->project_id, $data);

        $this->assertNull($result['project']->description);
    }

    // ===== プロジェクト削除テスト =====

    /**
     * 正常にプロジェクトを削除できること
     */
    public function test_deleteProject_success(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id);

        $result = $this->projectService->deleteProject($customer->customer_id, $project->project_id);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('プロジェクトを削除しました', $result['message']);

        // 論理削除されていることを確認
        $project->refresh();
        $this->assertTrue($project->del_flg);
    }

    /**
     * プロジェクトが存在しない場合にエラーが発生すること
     */
    public function test_deleteProject_fails_when_project_not_found(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません。');

        $this->projectService->deleteProject($customer->customer_id, 999);
    }

    /**
     * オーナーでない場合にエラーが発生すること
     */
    public function test_deleteProject_fails_when_not_owner(): void
    {
        $owner = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $project = $this->createProject($owner->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません。');

        $this->projectService->deleteProject($otherCustomer->customer_id, $project->project_id);
    }

    /**
     * 既に削除されたプロジェクトを削除しようとした場合にエラーが発生すること
     */
    public function test_deleteProject_fails_when_already_deleted(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer->customer_id, [
            'del_flg' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません。');

        $this->projectService->deleteProject($customer->customer_id, $project->project_id);
    }
}
