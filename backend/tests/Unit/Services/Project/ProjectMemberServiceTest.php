<?php

namespace Tests\Unit\Services\Project;

use App\Services\Project\ProjectMemberService;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectMemberService $projectMemberService;
    protected ProjectRole $ownerRole;
    protected ProjectRole $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectMemberService = app(ProjectMemberService::class);

        // マイグレーションで作成されたロールを取得
        // RefreshDatabaseにより、マイグレーション実行時に既にownerとmemberロールが作成されている
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
    protected function createProjectMember(int $projectId, int $customerId, int $projectMemberId = 2, float $splitWeight = 1.00): ProjectMember
    {
        return ProjectMember::create([
            'project_id' => $projectId,
            'project_member_id' => $projectMemberId,
            'customer_id' => $customerId,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => $splitWeight,
            'del_flg' => false,
        ]);
    }

    // ==== プロジェクトのメンバー一覧を取得テスト =====
    /**
     * customer_idとproject_idを指定して、プロジェクトのメンバー一覧を取得(オーナーのみ)
     */
    public function test_getProjectMembers_success_with_owner_only(): void
    {
        // テストデータの準備
        $customer = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($customer->customer_id);

        $projectMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 1,
            'customer_id' => $customer->customer_id,
            'role_id' => $this->ownerRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        // テスト実行
        $result = $this->projectMemberService->getProjectMembers($customer->customer_id, $project->project_id);

        // アサーション
        $this->assertArrayHasKey('members', $result);
        $this->assertCount(1, $result['members']);
        $this->assertEquals($customer->customer_id, $result['members'][0]['customer_id']);
        $this->assertEquals('owner', $result['members'][0]['role']);
        $this->assertEquals('オーナー', $result['members'][0]['role_name']);
        $this->assertEquals('オーナー', $result['members'][0]['name']);
    }

    /**
     * customer_idとproject_idを指定して、プロジェクトのメンバー一覧を取得(メンバーのみ)
     */
    public function test_getProjectMembers_success_with_members_only(): void
    {
        // テストデータの準備
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $projectMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 1,
            'customer_id' => $member->customer_id,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        // テスト実行
        $result = $this->projectMemberService->getProjectMembers($member->customer_id, $project->project_id);

        // アサーション
        $this->assertArrayHasKey('members', $result);
        $this->assertCount(1, $result['members']);
        $this->assertEquals($member->customer_id, $result['members'][0]['customer_id']);
        $this->assertEquals('member', $result['members'][0]['role']);
        $this->assertEquals('メンバー', $result['members'][0]['role_name']);
    }

    /**
     * customer_idとproject_idを指定して、プロジェクトのメンバー一覧を取得(オーナーとメンバー)
     */
    public function test_getProjectMembers_success_with_owner_and_members(): void
    {
        // テストデータの準備
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $member1 = $this->createCustomer([
            'email' => 'member1@example.com',
            'nick_name' => 'メンバー1',
        ]);

        $member2 = $this->createCustomer([
            'email' => 'member2@example.com',
            'nick_name' => 'メンバー2',
        ]);

        $project = $this->createProject($owner->customer_id);

        $ownerMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 1,
            'customer_id' => $owner->customer_id,
            'role_id' => $this->ownerRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        $member1ProjectMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 2,
            'customer_id' => $member1->customer_id,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        $member2ProjectMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 3,
            'customer_id' => $member2->customer_id,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        // テスト実行
        $result = $this->projectMemberService->getProjectMembers($owner->customer_id, $project->project_id);

        // アサーション
        $this->assertArrayHasKey('members', $result);
        $this->assertCount(3, $result['members']);
        
        // オーナーが含まれていることを確認
        $ownerFound = false;
        $member1Found = false;
        $member2Found = false;
        
        foreach ($result['members'] as $member) {
            if ($member['customer_id'] === $owner->customer_id && $member['role'] === 'owner') {
                $ownerFound = true;
            }
            if ($member['customer_id'] === $member1->customer_id && $member['role'] === 'member') {
                $member1Found = true;
            }
            if ($member['customer_id'] === $member2->customer_id && $member['role'] === 'member') {
                $member2Found = true;
            }
        }
        
        $this->assertTrue($ownerFound, 'オーナーがメンバー一覧に含まれていること');
        $this->assertTrue($member1Found, 'メンバー1がメンバー一覧に含まれていること');
        $this->assertTrue($member2Found, 'メンバー2がメンバー一覧に含まれていること');
    }

    /**
     * プロジェクトが存在しない場合にエラーが発生すること
     */
    public function test_getProjectMembers_fails_when_project_not_found(): void
    {
        $customer = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません');

        $this->projectMemberService->getProjectMembers($customer->customer_id, 999);
    }

    /**
     * アクセス権限がない場合にエラーが発生すること
     */
    public function test_getProjectMembers_fails_when_no_access_permission(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $unauthorizedCustomer = $this->createCustomer([
            'email' => 'unauthorized@example.com',
            'nick_name' => '未承認ユーザー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('アクセス権限がありません');

        $this->projectMemberService->getProjectMembers($unauthorizedCustomer->customer_id, $project->project_id);
    }

    /**
     * del_flg=trueのメンバーが除外されること
     */
    public function test_getProjectMembers_excludes_deleted_members(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $ownerMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 1,
            'customer_id' => $owner->customer_id,
            'role_id' => $this->ownerRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        // 削除されたメンバー
        $deletedMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 2,
            'customer_id' => $member->customer_id,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => true, // 削除フラグがtrue
        ]);

        $result = $this->projectMemberService->getProjectMembers($owner->customer_id, $project->project_id);

        $this->assertArrayHasKey('members', $result);
        $this->assertCount(1, $result['members']);
        $this->assertEquals($owner->customer_id, $result['members'][0]['customer_id']);
        
        // 削除されたメンバーが含まれていないことを確認
        foreach ($result['members'] as $memberData) {
            $this->assertNotEquals($member->customer_id, $memberData['customer_id'], '削除されたメンバーが含まれていないこと');
        }
    }

    /**
     * メンバーが0人の場合に空のCollectionが返されること
     */
    public function test_getProjectMembers_returns_empty_collection_when_no_members(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // メンバーを追加しない（オーナーもProjectMemberテーブルに登録されていない）

        $result = $this->projectMemberService->getProjectMembers($owner->customer_id, $project->project_id);

        $this->assertArrayHasKey('members', $result);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result['members']);
        $this->assertCount(0, $result['members']);
        $this->assertTrue($result['members']->isEmpty());
    }

    /**
     * メールアドレスなしのメンバー（customer_idがnull）が正しく取得されること
     */
    public function test_getProjectMembers_success_with_member_without_email(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // メールアドレスなしのメンバーを作成
        $guestMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 2,
            'customer_id' => null,
            'member_name' => 'ゲストメンバー',
            'member_email' => 'guest@example.com',
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);

        $result = $this->projectMemberService->getProjectMembers($owner->customer_id, $project->project_id);

        $this->assertArrayHasKey('members', $result);
        $this->assertCount(1, $result['members']);
        
        $member = $result['members'][0];
        $this->assertNull($member['customer_id']);
        $this->assertEquals('ゲストメンバー', $member['name']);
        $this->assertEquals('guest@example.com', $member['email']);
        $this->assertTrue($member['is_guest']); // customer_idがnullの場合はis_guestがtrue
    }

    /**
     * formatMemberDataの全フィールドが正しく返されること
     */
    public function test_getProjectMembers_returns_all_formatMemberData_fields(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
            'first_name' => '太郎',
            'last_name' => '山田',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
            'is_guest' => false,
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 2.50);
        $projectMember->update(['memo' => 'テストメモ']);

        $result = $this->projectMemberService->getProjectMembers($owner->customer_id, $project->project_id);

        $this->assertArrayHasKey('members', $result);
        $this->assertCount(1, $result['members']);
        
        $memberData = $result['members'][0];
        
        // formatMemberDataの全フィールドを確認
        $this->assertArrayHasKey('id', $memberData);
        $this->assertArrayHasKey('project_member_id', $memberData);
        $this->assertArrayHasKey('customer_id', $memberData);
        $this->assertArrayHasKey('role', $memberData);
        $this->assertArrayHasKey('role_name', $memberData);
        $this->assertArrayHasKey('split_weight', $memberData);
        $this->assertArrayHasKey('memo', $memberData);
        $this->assertArrayHasKey('name', $memberData);
        $this->assertArrayHasKey('email', $memberData);
        $this->assertArrayHasKey('is_guest', $memberData);
        $this->assertArrayHasKey('joined_at', $memberData);
        $this->assertArrayHasKey('total_expense', $memberData);
        
        // 値の確認
        $this->assertEquals($projectMember->id, $memberData['id']);
        $this->assertEquals(2, $memberData['project_member_id']);
        $this->assertEquals($member->customer_id, $memberData['customer_id']);
        $this->assertEquals('member', $memberData['role']);
        $this->assertEquals('メンバー', $memberData['role_name']);
        $this->assertEquals('2.50', (string)$memberData['split_weight']);
        $this->assertEquals('テストメモ', $memberData['memo']);
        $this->assertEquals('メンバー', $memberData['name']); // nick_nameが優先
        $this->assertEquals('member@example.com', $memberData['email']);
        $this->assertFalse($memberData['is_guest']);
        $this->assertNotNull($memberData['joined_at']);
        $this->assertIsNumeric($memberData['total_expense']); // ProjectTaskがない場合は0
    }

    // ==== プロジェクトのメンバー追加テスト =====
    /**
     * メールアドレスありの新規メンバーを追加（正常系）
     * フロントエンドで重複チェックが行われるため、オーナーが自分自身を追加することは想定しない
     */
    public function test_addProjectMember_success_with_email(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // 新しいメンバーを追加（メールアドレスあり）
        $result = $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => '新メンバー',
            'email' => 'newmember@example.com',
        ]);

        $this->assertArrayHasKey('member', $result);
        $this->assertEquals('member', $result['member']['role']); // addProjectMemberは常にmemberロール
        $this->assertEquals('メンバー', $result['member']['role_name']);
        $this->assertEquals('新メンバー', $result['member']['name']);
        $this->assertEquals('newmember@example.com', $result['member']['email']);
        $this->assertNotNull($result['member']['customer_id']);
    }

    /**
     * メールアドレスなしの新規メンバーを追加（正常系）
     */
    public function test_addProjectMember_success_without_email(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // メールアドレスなしのメンバーを追加
        $result = $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => 'ゲストメンバー',
            'email' => null,
        ]);

        $this->assertArrayHasKey('member', $result);
        $this->assertEquals('member', $result['member']['role']);
        $this->assertEquals('メンバー', $result['member']['role_name']);
        $this->assertEquals('ゲストメンバー', $result['member']['name']);
        $this->assertNull($result['member']['customer_id']); // メールアドレスなしの場合はcustomer_idがnull
    }

    /**
     * 既存のメールアドレスでメンバーを追加（既存顧客を再利用）
     */
    public function test_addProjectMember_success_with_existing_customer(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $existingCustomer = $this->createCustomer([
            'email' => 'existing@example.com',
            'nick_name' => '既存ユーザー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // 既存のメールアドレスでメンバーを追加
        $result = $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => '既存ユーザー',
            'email' => 'existing@example.com',
        ]);

        $this->assertArrayHasKey('member', $result);
        $this->assertEquals('member', $result['member']['role']);
        $this->assertEquals($existingCustomer->customer_id, $result['member']['customer_id']); // 既存顧客のIDが使用される
        $this->assertEquals('existing@example.com', $result['member']['email']);
    }

    /**
     * 重複メンバー追加のエラー（メールアドレスあり）（バックエンドでも重複チェック）
     * フロントエンドで弾かれる想定だが、バックエンドでも防御
     */
    public function test_addProjectMember_fails_when_duplicate_member_with_email(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // 最初のメンバー追加
        $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => 'メンバー',
            'email' => 'member@example.com',
        ]);

        // 同じメンバーを再度追加しようとする（重複エラー）
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('このメンバーは既に追加されています');

        $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => 'メンバー',
            'email' => 'member@example.com',
        ]);
    }

    /**
     * 重複メンバー追加のエラー（メールアドレスなし、member_nameでチェック）
     */
    public function test_addProjectMember_fails_when_duplicate_member_without_email(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // 最初のメンバー追加（メールアドレスなし）
        $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => 'ゲストメンバー',
            'email' => null,
        ]);

        // 同じ名前のメンバーを再度追加しようとする（重複エラー）
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('このメンバーは既に追加されています');

        $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => 'ゲストメンバー',
            'email' => null,
        ]);
    }

    /**
     * メンバー追加でnameが必須でない場合（バリデーションエラー）
     */
    public function test_addProjectMember_fails_when_name_missing(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // nameが指定されていない（バリデーションエラー）
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'email' => 'member@example.com',
        ]);
    }

    /**
     * メンバー追加でnameが最大長を超える場合（バリデーションエラー）
     */
    public function test_addProjectMember_fails_when_name_exceeds_maximum_length(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // nameが256文字（最大255文字を超える）
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => str_repeat('a', 256),
            'email' => 'member@example.com',
        ]);
    }

    /**
     * メンバー追加でemailがメール形式でない場合（バリデーションエラー）
     */
    public function test_addProjectMember_fails_when_invalid_email_format(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // 無効なメール形式
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => 'メンバー',
            'email' => 'invalid-email',
        ]);
    }

    /**
     * メンバー追加でemailが最大長を超える場合（バリデーションエラー）
     */
    public function test_addProjectMember_fails_when_email_exceeds_maximum_length(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // emailが256文字（最大255文字を超える）
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->projectMemberService->addProjectMember($owner->customer_id, $project->project_id, [
            'name' => 'メンバー',
            'email' => str_repeat('a', 250) . '@example.com', // 256文字以上
        ]);
    }

    /**
     * メンバー追加でオーナー権限がない場合（エラー）
     */
    public function test_addProjectMember_fails_when_no_owner_permission(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $unauthorizedCustomer = $this->createCustomer([
            'email' => 'unauthorized@example.com',
            'nick_name' => '未承認ユーザー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // オーナー権限のないユーザーが追加しようとする
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('オーナー権限がありません');

        $this->projectMemberService->addProjectMember($unauthorizedCustomer->customer_id, $project->project_id, [
            'name' => 'メンバー',
            'email' => 'member@example.com',
        ]);
    }

    /**
     * メンバー追加でプロジェクトが存在しない場合（エラー）
     */
    public function test_addProjectMember_fails_when_project_not_found(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        // 存在しないプロジェクトIDで追加
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません');

        $this->projectMemberService->addProjectMember($owner->customer_id, 999, [
            'name' => 'メンバー',
            'email' => 'member@example.com',
        ]);
    }

    // ==== プロジェクトのメンバー更新テスト =====
    /**
     * メンバーの比重を更新（正常系）
     */
    public function test_updateMemberWeight_success(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        // テストの独立性を保つため、メンバーを直接作成（addProjectMemberを使わない）
        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMemberId = $projectMember->project_member_id;

        // 比重を更新
        $result = $this->projectMemberService->updateMemberWeight($owner->customer_id, $project->project_id, $projectMemberId, [
            'split_weight' => 2.50,
        ]);

        $this->assertArrayHasKey('split_weight', $result);
        $this->assertEquals('2.50', (string)$result['split_weight']);

        // データベースで確認
        $updatedMember = ProjectMember::where('project_id', $project->project_id)
            ->where('project_member_id', $projectMemberId)
            ->first();
        $this->assertEquals('2.50', (string)$updatedMember->split_weight);
    }

    /**
     * メンバーの比重更新で最小値（0.01）を設定（正常系）
     */
    public function test_updateMemberWeight_success_with_minimum_value(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMemberId = $projectMember->project_member_id;

        // 最小値で更新
        $result = $this->projectMemberService->updateMemberWeight($owner->customer_id, $project->project_id, $projectMemberId, [
            'split_weight' => 0.01,
        ]);

        $this->assertEquals('0.01', (string)$result['split_weight']);
    }

    /**
     * メンバーの比重更新で最大値（999.99）を設定（正常系）
     */
    public function test_updateMemberWeight_success_with_maximum_value(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMemberId = $projectMember->project_member_id;

        // 最大値で更新
        $result = $this->projectMemberService->updateMemberWeight($owner->customer_id, $project->project_id, $projectMemberId, [
            'split_weight' => 999.99,
        ]);

        $this->assertEquals('999.99', (string)$result['split_weight']);
    }

    /**
     * メンバーの比重更新で最小値未満の値を設定（エラー）
     */
    public function test_updateMemberWeight_fails_when_below_minimum(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMemberId = $projectMember->project_member_id;

        // 最小値未満で更新（バリデーションエラー）
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->projectMemberService->updateMemberWeight($owner->customer_id, $project->project_id, $projectMemberId, [
            'split_weight' => 0.00,
        ]);
    }

    /**
     * メンバーの比重更新で最大値を超える値を設定（エラー）
     */
    public function test_updateMemberWeight_fails_when_above_maximum(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMemberId = $projectMember->project_member_id;

        // 最大値を超える値で更新（バリデーションエラー）
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->projectMemberService->updateMemberWeight($owner->customer_id, $project->project_id, $projectMemberId, [
            'split_weight' => 1000.00,
        ]);
    }

    /**
     * メンバーの比重更新で存在しないメンバーIDを指定（エラー）
     */
    public function test_updateMemberWeight_fails_when_member_not_found(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // 存在しないメンバーIDで更新
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('メンバーが見つかりません');

        $this->projectMemberService->updateMemberWeight($owner->customer_id, $project->project_id, 999, [
            'split_weight' => 2.00,
        ]);
    }

    /**
     * メンバーの比重更新でオーナー権限がない場合（エラー）
     */
    public function test_updateMemberWeight_fails_when_no_owner_permission(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $unauthorizedCustomer = $this->createCustomer([
            'email' => 'unauthorized@example.com',
            'nick_name' => '未承認ユーザー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMemberId = $projectMember->project_member_id;

        // オーナー権限のないユーザーが更新しようとする
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('オーナー権限がありません');

        $this->projectMemberService->updateMemberWeight($unauthorizedCustomer->customer_id, $project->project_id, $projectMemberId, [
            'split_weight' => 2.00,
        ]);
    }

    /**
     * メンバーの比重更新で削除されたメンバーを指定（エラー）
     */
    public function test_updateMemberWeight_fails_when_member_deleted(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        // 削除されたメンバーを作成
        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMember->update(['del_flg' => true]);
        $projectMemberId = $projectMember->project_member_id;

        // 削除されたメンバーを更新しようとする
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('メンバーが見つかりません');

        $this->projectMemberService->updateMemberWeight($owner->customer_id, $project->project_id, $projectMemberId, [
            'split_weight' => 2.00,
        ]);
    }

    /**
     * メンバーの比重更新でプロジェクトが存在しない場合（エラー）
     */
    public function test_updateMemberWeight_fails_when_project_not_found(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        // 存在しないプロジェクトIDで更新
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません');

        $this->projectMemberService->updateMemberWeight($owner->customer_id, 999, 2, [
            'split_weight' => 2.00,
        ]);
    }

    // ==== プロジェクトのメンバーメモ更新テスト =====
    /**
     * メンバーのメモを更新（正常系）
     */
    public function test_updateMemberMemo_success(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        // テストの独立性を保つため、メンバーを直接作成
        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $memberId = $projectMember->project_member_id; // updateMemberMemoはproject_member_idを使用

        // メモを更新
        $result = $this->projectMemberService->updateMemberMemo($owner->customer_id, $project->project_id, $memberId, [
            'memo' => 'テストメモ',
        ]);

        $this->assertArrayHasKey('memo', $result);
        $this->assertEquals('テストメモ', $result['memo']);

        // データベースで確認
        $updatedMember = ProjectMember::where('project_id', $project->project_id)
            ->where('project_member_id', $memberId)
            ->first();
        $this->assertEquals('テストメモ', $updatedMember->memo);
    }

    /**
     * メンバーのメモを削除（nullに設定）（正常系）
     */
    public function test_updateMemberMemo_success_with_null(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        // メモが既に設定されているメンバーを作成
        $projectMember = ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 2,
            'customer_id' => $member->customer_id,
            'role_id' => $this->memberRole->role_id,
            'split_weight' => 1.00,
            'memo' => '既存のメモ',
            'del_flg' => false,
        ]);
        $memberId = $projectMember->project_member_id;

        // メモをnullに更新
        $result = $this->projectMemberService->updateMemberMemo($owner->customer_id, $project->project_id, $memberId, [
            'memo' => null,
        ]);

        $this->assertNull($result['memo']);

        // データベースで確認
        $updatedMember = ProjectMember::where('project_id', $project->project_id)
            ->where('project_member_id', $memberId)
            ->first();
        $this->assertNull($updatedMember->memo);
    }

    /**
     * メンバーのメモ更新で最大長（1000文字）を設定（正常系）
     */
    public function test_updateMemberMemo_success_with_maximum_length(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $memberId = $projectMember->project_member_id;

        // 最大長のメモを設定
        $maxMemo = str_repeat('a', 1000);
        $result = $this->projectMemberService->updateMemberMemo($owner->customer_id, $project->project_id, $memberId, [
            'memo' => $maxMemo,
        ]);

        $this->assertEquals($maxMemo, $result['memo']);
    }

    /**
     * メンバーのメモ更新で最大長を超える値を設定（エラー）
     */
    public function test_updateMemberMemo_fails_when_exceeds_maximum_length(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $memberId = $projectMember->project_member_id;

        // 最大長を超えるメモを設定（バリデーションエラー）
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->projectMemberService->updateMemberMemo($owner->customer_id, $project->project_id, $memberId, [
            'memo' => str_repeat('a', 1001),
        ]);
    }

    /**
     * メンバーのメモ更新で存在しないメンバーIDを指定（エラー）
     */
    public function test_updateMemberMemo_fails_when_member_not_found(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // 存在しないメンバーIDで更新
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('メンバーが見つかりません');

        $this->projectMemberService->updateMemberMemo($owner->customer_id, $project->project_id, 999, [
            'memo' => 'テストメモ',
        ]);
    }

    /**
     * メンバーのメモ更新でオーナー権限がない場合（エラー）
     */
    public function test_updateMemberMemo_fails_when_no_owner_permission(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $unauthorizedCustomer = $this->createCustomer([
            'email' => 'unauthorized@example.com',
            'nick_name' => '未承認ユーザー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $memberId = $projectMember->project_member_id;

        // オーナー権限のないユーザーが更新しようとする
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('オーナー権限がありません');

        $this->projectMemberService->updateMemberMemo($unauthorizedCustomer->customer_id, $project->project_id, $memberId, [
            'memo' => 'テストメモ',
        ]);
    }

    /**
     * メンバーのメモ更新で削除されたメンバーを指定（エラー）
     */
    public function test_updateMemberMemo_fails_when_member_deleted(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        // 削除されたメンバーを作成
        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMember->update(['del_flg' => true]);
        $memberId = $projectMember->project_member_id;

        // 削除されたメンバーを更新しようとする
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('メンバーが見つかりません');

        $this->projectMemberService->updateMemberMemo($owner->customer_id, $project->project_id, $memberId, [
            'memo' => 'テストメモ',
        ]);
    }

    // ==== プロジェクトのメンバー削除テスト =====
    /**
     * メンバーを削除（正常系）
     */
    public function test_removeProjectMember_success(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        // テストの独立性を保つため、メンバーを直接作成
        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMemberId = $projectMember->project_member_id;

        // メンバーを削除
        $result = $this->projectMemberService->removeProjectMember($owner->customer_id, $project->project_id, $projectMemberId);

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('メンバーが正常に削除されました', $result['message']);

        // データベースで確認（論理削除）
        $deletedMember = ProjectMember::where('project_id', $project->project_id)
            ->where('project_member_id', $projectMemberId)
            ->first();
        $this->assertTrue($deletedMember->del_flg);
    }

    /**
     * メンバー削除で存在しないメンバーIDを指定（エラー）
     */
    public function test_removeProjectMember_fails_when_member_not_found(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        // 存在しないメンバーIDで削除
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('メンバーが見つかりません');

        $this->projectMemberService->removeProjectMember($owner->customer_id, $project->project_id, 999);
    }

    /**
     * メンバー削除でオーナー権限がない場合（エラー）
     */
    public function test_removeProjectMember_fails_when_no_owner_permission(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $unauthorizedCustomer = $this->createCustomer([
            'email' => 'unauthorized@example.com',
            'nick_name' => '未承認ユーザー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMemberId = $projectMember->project_member_id;

        // オーナー権限のないユーザーが削除しようとする
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('オーナー権限がありません');

        $this->projectMemberService->removeProjectMember($unauthorizedCustomer->customer_id, $project->project_id, $projectMemberId);
    }

    /**
     * メンバー削除で削除されたメンバーを指定（エラー）
     */
    public function test_removeProjectMember_fails_when_member_already_deleted(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        $project = $this->createProject($owner->customer_id);

        $member = $this->createCustomer([
            'email' => 'member@example.com',
            'nick_name' => 'メンバー',
        ]);

        // 既に削除されたメンバーを作成
        $projectMember = $this->createProjectMember($project->project_id, $member->customer_id, 2, 1.00);
        $projectMember->update(['del_flg' => true]);
        $projectMemberId = $projectMember->project_member_id;

        // 既に削除されたメンバーを削除しようとする
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('メンバーが見つかりません');

        $this->projectMemberService->removeProjectMember($owner->customer_id, $project->project_id, $projectMemberId);
    }

    /**
     * メンバー削除でプロジェクトが存在しない場合（エラー）
     */
    public function test_removeProjectMember_fails_when_project_not_found(): void
    {
        $owner = $this->createCustomer([
            'email' => 'owner@example.com',
            'nick_name' => 'オーナー',
        ]);

        // 存在しないプロジェクトIDで削除
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('プロジェクトが見つかりません');

        $this->projectMemberService->removeProjectMember($owner->customer_id, 999, 2);
    }
}
