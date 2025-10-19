<?php

namespace App\Services\Project;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectMember;
use App\Models\ProjectTaskMember;
use App\Services\BaseService;

class ProjectTaskService extends BaseService
{

    /**
     * プロジェクトの会計一覧を取得
     */
    public function getProjectTasks($customerId, int $projectId): array
    {
        // プロジェクトの存在確認とアクセス権限チェック
        $this->validateProjectAccess($customerId, $projectId);

        $projectTasks = ProjectTask::where('project_id', $projectId)
            ->where('del_flg', false)
            ->with(['taskMembers.projectMember.customer'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($task) {
                return $this->formatTaskData($task);
            });

        return $this->successResponse('会計一覧を取得しました', ['accountings' => $projectTasks]);
    }

    /**
     * 会計を追加
     */
    public function createProjectTask($customerId, int $projectId, array $data): array
    {
        $this->logInfo('会計追加開始', [
            'customer_id' => $customerId,
            'project_id' => $projectId,
            'request_data' => $data
        ]);

        // バリデーション
        $validated = $this->validateData($data, [
            'accounting_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
            'accounting_type' => 'nullable|string|max:50',
            'member_name' => 'required|string|max:255',
            'target_member_ids' => 'nullable|array',
            'target_member_ids.*' => 'integer|exists:project_members,id',
        ]);

        // プロジェクトの存在確認とオーナー権限チェック
        $project = $this->validateOwnerAccess($customerId, $projectId);

        return $this->executeInTransaction(function () use ($project, $projectId, $validated, $customerId) {
            // プロジェクトタスクコードを生成
            $nextTaskCode = $this->generateNextTaskCode($project);

            // 支払人情報を取得
            $payerInfo = $this->getPayerInfo($project, $validated['member_name'], $customerId);

            // プロジェクトタスクデータを作成
            $projectTaskData = $this->buildProjectTaskData($projectId, $nextTaskCode, $validated, $payerInfo);

            // プロジェクトタスクを作成
            $projectTask = ProjectTask::create($projectTaskData);

            // 対象メンバーを追加
            if (!empty($validated['target_member_ids'])) {
                $this->addTargetMembers($projectTask->task_id, $validated['target_member_ids']);
            }

            $this->logInfo('会計追加完了', [
                'project_id' => $projectId,
                'task_id' => $projectTask->task_id
            ]);

            return $this->successResponse('会計を追加しました', ['accounting' => $projectTask]);
        });
    }

    /**
     * 会計を更新
     */
    public function updateProjectTask($customerId, int $projectId, int $taskId, array $data): array
    {
        // バリデーション
        $validated = $this->validateData($data, [
            'accounting_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
            'accounting_type' => 'nullable|string|max:50',
            'member_name' => 'required|string|max:255',
            'target_member_ids' => 'nullable|array',
            'target_member_ids.*' => 'integer|exists:project_members,id',
        ]);

        // オーナー権限チェック
        $this->validateOwnerAccess($customerId, $projectId);

        // プロジェクトタスクを取得
        $projectTask = $this->getProjectTask($projectId, $taskId);

        return $this->executeInTransaction(function () use ($projectTask, $validated, $taskId) {
            // プロジェクトタスクを更新
            $projectTask->update([
                'task_name' => $validated['accounting_name'],
                'task_member_name' => $validated['member_name'],
                'accounting_amount' => $validated['amount'],
                'accounting_type' => $validated['accounting_type'] ?? 'expense',
                'breakdown' => $validated['description'],
            ]);

            // 対象メンバーを更新
            if (isset($validated['target_member_ids'])) {
                $this->updateTargetMembers($taskId, $validated['target_member_ids']);
            }

            return $this->successResponse('会計を更新しました', ['accounting' => $projectTask->fresh()]);
        });
    }

    /**
     * 会計を削除（論理削除）
     */
    public function deleteProjectTask($customerId, int $projectId, int $taskId): array
    {
        // オーナー権限チェック
        $this->validateOwnerAccess($customerId, $projectId);

        // プロジェクトタスクを取得
        $projectTask = $this->getProjectTask($projectId, $taskId);

        // 論理削除
        $this->softDelete($projectTask);

        return $this->successResponse('会計を削除しました');
    }


    /**
     * プロジェクトタスクを取得
     */
    private function getProjectTask(int $projectId, int $taskId): ProjectTask
    {
        $projectTask = ProjectTask::where('project_id', $projectId)
            ->where('task_id', $taskId)
            ->where('del_flg', false)
            ->first();
        
        if (!$projectTask) {
            throw new \Exception('会計が見つかりません');
        }

        return $projectTask;
    }

    /**
     * 次のタスクコードを生成
     */
    private function generateNextTaskCode(Project $project): int
    {
        $maxTaskCode = $project->projectTasks()->max('project_task_code') ?? 0;
        return $maxTaskCode + 1;
    }

    /**
     * 支払人情報を取得
     */
    private function getPayerInfo(Project $project, string $memberName, $customerId): array
    {
        // 支払人（task_member_name）のプロジェクトメンバーIDを取得
        $payerMember = ProjectMember::where('project_id', $project->project_id)
            ->where('member_name', $memberName)
            ->where('del_flg', false)
            ->first();

        // オーナーの名前を取得（オーナーが支払った場合の判定用）
        $ownerName = $this->getOwnerName($project);

        if ($payerMember) {
            return [
                'type' => 'member',
                'member_id' => $payerMember->id,
                'customer_id' => null
            ];
        } elseif ($memberName === $ownerName) {
            return [
                'type' => 'owner',
                'member_id' => null,
                'customer_id' => $project->customer->customer_id
            ];
        } else {
            return [
                'type' => 'guest',
                'member_id' => null,
                'customer_id' => $customerId
            ];
        }
    }

    /**
     * プロジェクトタスクデータを構築
     */
    private function buildProjectTaskData(int $projectId, int $nextTaskCode, array $validated, array $payerInfo): array
    {
        return [
            'project_id' => $projectId,
            'project_task_code' => $nextTaskCode,
            'task_name' => $validated['accounting_name'],
            'task_member_name' => $validated['member_name'],
            'accounting_amount' => $validated['amount'],
            'accounting_type' => $validated['accounting_type'] ?? 'expense',
            'breakdown' => $validated['description'],
            'memo' => null,
            'del_flg' => false,
            'member_id' => $payerInfo['member_id'],
            'customer_id' => $payerInfo['customer_id'],
        ];
    }

    /**
     * 対象メンバーを追加
     */
    private function addTargetMembers(int $taskId, array $targetMemberIds): void
    {
        foreach ($targetMemberIds as $memberId) {
            ProjectTaskMember::create([
                'member_id' => $memberId,
                'task_id' => $taskId,
                'del_flg' => false,
            ]);
        }
    }

    /**
     * 対象メンバーを更新
     */
    private function updateTargetMembers(int $taskId, array $targetMemberIds): void
    {
        // 既存の対象メンバーを論理削除
        ProjectTaskMember::where('task_id', $taskId)
            ->update(['del_flg' => true]);

        // 新しい対象メンバーを追加
        $this->addTargetMembers($taskId, $targetMemberIds);
    }

    /**
     * タスクデータをフォーマット
     */
    private function formatTaskData(ProjectTask $task): array
    {
        // 対象メンバーの名前とIDを取得
        $targetMembers = $task->taskMembers->map(function ($taskMember) {
            $member = $taskMember->projectMember;
            if (!$member) return null;
            
            $memberName = $this->getMemberName($member);
            
            return [
                'id' => $member->id,
                'name' => $memberName
            ];
        })->filter()->toArray();
        
        $taskArray = $task->toArray();
        $taskArray['target_members'] = array_column($targetMembers, 'name');
        $taskArray['target_member_ids'] = array_column($targetMembers, 'id');
        
        return $taskArray;
    }
}
