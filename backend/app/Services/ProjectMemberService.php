<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use App\Models\ProjectTask;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

class ProjectMemberService
{
    protected ValidationFactory $validationFactory;
    protected ConnectionInterface $db;
    protected LoggerInterface $logger;

    public function __construct(
        ValidationFactory $validationFactory,
        ConnectionInterface $db,
        LoggerInterface $logger
    ) {
        $this->validationFactory = $validationFactory;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * プロジェクトのメンバー一覧を取得
     */
    public function getProjectMembers($customerId, int $projectId): array
    {
        // プロジェクトの存在確認とアクセス権限チェック
        $this->validateProjectAccess($customerId, $projectId);

        // メンバー一覧を取得（プロジェクトオーナーも含む）
        $members = ProjectMember::where('project_id', $projectId)
                              ->where('del_flg', false)
                              ->with(['customer', 'role'])
                              ->get()
                              ->map(function ($member) use ($projectId) {
                                  return $this->formatMemberData($member, $projectId);
                              });

        return [
            'members' => $members
        ];
    }

    /**
     * プロジェクトにメンバーを追加
     */
    public function addProjectMember($customerId, int $projectId, array $data): array
    {
        $this->logger->info('メンバー追加開始', [
            'customer_id' => $customerId,
            'project_id' => $projectId,
            'request_data' => $data
        ]);

        // バリデーション
        $validator = $this->validationFactory->make($data, [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        // プロジェクトの存在確認とオーナー権限チェック
        $this->validateOwnerAccess($customerId, $projectId);

        $this->db->beginTransaction();

        try {
            $email = $validated['email'] ?? null;
            $name = $validated['name'];

            // 顧客の処理（メールアドレスがある場合）
            $customerId = $this->processCustomerForMember($email, $name);

            // 重複チェック
            $this->validateMemberNotExists($projectId, $customerId, $name);

            // メンバーとして追加
            $member = $this->createProjectMember($projectId, $customerId, $name, $email);

            $this->db->commit();

            $this->logger->info('メンバー追加完了', [
                'project_id' => $projectId,
                'member_id' => $member->id
            ]);

            return [
                'message' => 'メンバーが正常に追加されました',
                'member' => $this->formatMemberData($member, $projectId)
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * メンバーのメモを更新
     */
    public function updateMemberMemo($customerId, int $projectId, int $memberId, array $data): array
    {
        // バリデーション
        $validator = $this->validationFactory->make($data, [
            'memo' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // オーナー権限チェック
        $this->validateOwnerAccess($customerId, $projectId);

        // メンバーを更新
        $projectMember = $this->getProjectMember($projectId, $memberId);
        $projectMember->update(['memo' => $data['memo'] ?? null]);

        return [
            'message' => 'メモが正常に更新されました',
            'memo' => $projectMember->memo
        ];
    }

    /**
     * メンバーの比重を更新
     */
    public function updateMemberWeight($customerId, int $projectId, int $memberId, array $data): array
    {
        // バリデーション
        $validator = $this->validationFactory->make($data, [
            'split_weight' => 'required|numeric|min:0.01|max:999.99',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // オーナー権限チェック
        $this->validateOwnerAccess($customerId, $projectId);

        // メンバーを更新
        $projectMember = $this->getProjectMemberByProjectMemberId($projectId, $memberId);
        $projectMember->update(['split_weight' => $data['split_weight']]);

        return [
            'message' => '割り勘比重が正常に更新されました',
            'split_weight' => $projectMember->split_weight
        ];
    }

    /**
     * プロジェクトからメンバーを削除
     */
    public function removeProjectMember($customerId, int $projectId, int $memberId): array
    {
        // オーナー権限チェック
        $this->validateOwnerAccess($customerId, $projectId);

        // メンバーを論理削除
        $projectMember = $this->getProjectMemberByProjectMemberId($projectId, $memberId);
        $projectMember->update(['del_flg' => true]);

        return [
            'message' => 'メンバーが正常に削除されました'
        ];
    }

    /**
     * プロジェクトのアクセス権限をチェック
     */
    private function validateProjectAccess($customerId, int $projectId): void
    {
        $project = Project::where('project_id', $projectId)
            ->where('del_flg', false)
            ->first();
        
        if (!$project) {
            throw new \Exception('プロジェクトが見つかりません');
        }

        // プロジェクトのオーナーまたはメンバーかチェック
        $isOwner = $project->customer_id === $customerId;
        $isMember = ProjectMember::where('project_id', $projectId)
                                ->where('customer_id', $customerId)
                                ->where('del_flg', false)
                                ->exists();
        
        if (!$isOwner && !$isMember) {
            throw new \Exception('アクセス権限がありません');
        }
    }

    /**
     * プロジェクトのオーナー権限をチェック
     */
    private function validateOwnerAccess($customerId, int $projectId): void
    {
        $ownerRole = ProjectRole::where('role_code', 'owner')->first();
        if (!$ownerRole) {
            throw new \Exception('オーナーロールが見つかりません');
        }

        $isOwner = ProjectMember::where('project_id', $projectId)
                               ->where('customer_id', $customerId)
                               ->where('role_id', $ownerRole->role_id)
                               ->where('del_flg', false)
                               ->exists();
        
        if (!$isOwner) {
            throw new \Exception('オーナー権限がありません');
        }
    }

    /**
     * メンバーの重複をチェック
     */
    private function validateMemberNotExists(int $projectId, $customerId, string $name): void
    {
        if ($customerId) {
            // customer_idがある場合（メールアドレスあり）
            $existingMember = ProjectMember::where('project_id', $projectId)
                                          ->where('customer_id', $customerId)
                                          ->where('del_flg', false)
                                          ->exists();
        } else {
            // customer_idがnullの場合（メールアドレスなし）
            $existingMember = ProjectMember::where('project_id', $projectId)
                                          ->where('customer_id', null)
                                          ->where('member_name', $name)
                                          ->where('del_flg', false)
                                          ->exists();
        }
        
        if ($existingMember) {
            throw new \Exception('このメンバーは既に追加されています');
        }
    }

    /**
     * メンバー用の顧客を処理
     */
    private function processCustomerForMember(?string $email, string $name)
    {
        if ($email) {
            $existingCustomer = Customer::where('email', $email)
                                      ->where('del_flg', false)
                                      ->first();
            
            if ($existingCustomer) {
                return $existingCustomer->customer_id;
            } else {
                // ゲストユーザーとして新規作成
                $newCustomer = Customer::create([
                    'is_guest' => true,
                    'nick_name' => $name,
                    'email' => $email,
                    'del_flg' => false,
                ]);
                return $newCustomer->customer_id;
            }
        }
        
        return null;
    }

    /**
     * プロジェクトメンバーを作成
     */
    private function createProjectMember(int $projectId, $customerId, string $name, ?string $email): ProjectMember
    {
        $memberRole = ProjectRole::where('role_code', 'member')->first();
        if (!$memberRole) {
            throw new \Exception('メンバーロールが見つかりません');
        }

        // プロジェクト内での次のproject_member_idを取得
        $nextProjectMemberId = ProjectMember::where('project_id', $projectId)
                                          ->where('del_flg', false)
                                          ->max('project_member_id') + 1;

        return ProjectMember::create([
            'project_id' => $projectId,
            'project_member_id' => $nextProjectMemberId,
            'customer_id' => $customerId,
            'member_name' => $customerId ? null : $name,
            'member_email' => $customerId ? null : $email,
            'role_id' => $memberRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);
    }

    /**
     * プロジェクトメンバーを取得（IDで）
     */
    private function getProjectMember(int $projectId, int $memberId): ProjectMember
    {
        $projectMember = ProjectMember::where('id', $memberId)
                                     ->where('project_id', $projectId)
                                     ->where('del_flg', false)
                                     ->first();
        
        if (!$projectMember) {
            throw new \Exception('メンバーが見つかりません');
        }

        return $projectMember;
    }

    /**
     * プロジェクトメンバーを取得（project_member_idで）
     */
    private function getProjectMemberByProjectMemberId(int $projectId, int $memberId): ProjectMember
    {
        $projectMember = ProjectMember::where('project_member_id', $memberId)
                                     ->where('project_id', $projectId)
                                     ->where('del_flg', false)
                                     ->first();
        
        if (!$projectMember) {
            throw new \Exception('メンバーが見つかりません');
        }

        return $projectMember;
    }

    /**
     * メンバーデータをフォーマット
     */
    private function formatMemberData(ProjectMember $member, int $projectId): array
    {
        $memberName = $member->customer_id 
            ? ($member->customer->nick_name ?: 
               ($member->customer->first_name . ' ' . $member->customer->last_name))
            : $member->member_name;
        
        // メンバーの支出合計を計算
        $totalExpense = ProjectTask::where('project_id', $projectId)
            ->where('task_member_name', $memberName)
            ->where('accounting_type', 'expense')
            ->where('del_flg', false)
            ->sum('accounting_amount');
        
        return [
            'id' => $member->id,
            'project_member_id' => $member->project_member_id,
            'customer_id' => $member->customer_id,
            'role' => $member->role->role_code,
            'role_name' => $member->role->role_name,
            'split_weight' => $member->split_weight,
            'memo' => $member->memo,
            'name' => $memberName,
            'email' => $member->customer_id 
                ? $member->customer->email 
                : $member->member_email,
            'is_guest' => $member->customer_id 
                ? $member->customer->is_guest 
                : true,
            'joined_at' => $member->created_at,
            'total_expense' => (float) $totalExpense,
        ];
    }
}
