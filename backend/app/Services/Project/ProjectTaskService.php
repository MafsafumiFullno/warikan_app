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
            ->with(['taskMembers' => function ($query) {
                $query->where('del_flg', false);
            }, 'taskMembers.projectMember.customer'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($task) {
                return $this->formatTaskData($task);
            });

        return ['accountings' => $projectTasks];
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
            'member_id' => 'nullable|integer|exists:project_members,id',
            'member_name' => 'nullable|required_without:member_id|string|max:255',
            'target_member_ids' => 'nullable|array',
            'target_member_ids.*' => 'integer|exists:project_members,id',
        ]);

        // プロジェクトの存在確認とオーナー権限チェック
        $project = $this->validateOwnerAccess($customerId, $projectId);

        return $this->executeInTransaction(function () use ($project, $projectId, $validated, $customerId) {
            // プロジェクトタスクコードを生成
            $nextTaskCode = $this->generateNextTaskCode($project);

            // 支払人情報を取得
            $payerInfo = $this->resolvePayerInfo($project, $validated, $customerId);

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

            return ['accounting' => $this->formatTaskData($projectTask->fresh())];
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
            'member_id' => 'nullable|integer|exists:project_members,id',
            'member_name' => 'nullable|required_without:member_id|string|max:255',
            'target_member_ids' => 'nullable|array',
            'target_member_ids.*' => 'integer|exists:project_members,id',
        ]);

        // オーナー権限チェック
        $project = $this->validateOwnerAccess($customerId, $projectId);

        // プロジェクトタスクを取得
        $projectTask = $this->getProjectTask($projectId, $taskId);

        return $this->executeInTransaction(function () use ($project, $projectTask, $validated, $taskId, $customerId) {
            // 支払人情報を取得
            $payerInfo = $this->resolvePayerInfo($project, $validated, $customerId);

            // プロジェクトタスクを更新
            $projectTask->update([
                'task_name' => $validated['accounting_name'],
                'task_member_name' => $payerInfo['member_name'],
                'accounting_amount' => $validated['amount'],
                'accounting_type' => $validated['accounting_type'] ?? 'expense',
                'breakdown' => $validated['description'] ?? null,
                'member_id' => $payerInfo['member_id'],
            ]);

            // 対象メンバーを更新
            if (isset($validated['target_member_ids'])) {
                $this->updateTargetMembers($taskId, $validated['target_member_ids']);
            }

            return ['accounting' => $this->formatTaskData($projectTask->fresh())];
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

        return ['message' => '会計を削除しました'];
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
    private function resolvePayerInfo(Project $project, array $validated, $customerId): array
    {
        if (!empty($validated['member_id'])) {
            $payerMember = $this->getProjectMember($project->project_id, (int) $validated['member_id']);

            return [
                'member_id' => $payerMember->id,
                'member_name' => $this->getMemberName($payerMember),
            ];
        }

        return $this->getPayerInfoByName($project, $validated['member_name']);
    }

    /**
     * 支払人情報を名前から取得（旧入力の互換用）
     */
    private function getPayerInfoByName(Project $project, string $memberName): array
    {
        // 支払人（task_member_name）のプロジェクトメンバーIDを取得
        $payerMember = ProjectMember::where('project_id', $project->project_id)
            ->where('member_name', $memberName)
            ->where('del_flg', false)
            ->first();

        if ($payerMember) {
            return [
                'member_id' => $payerMember->id,
                'member_name' => $this->getMemberName($payerMember),
            ];
        }

        return [
            'member_id' => null,
            'member_name' => $memberName,
        ];
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
            'task_member_name' => $payerInfo['member_name'],
            'accounting_amount' => $validated['amount'],
            'accounting_type' => $validated['accounting_type'] ?? 'expense',
            'breakdown' => $validated['description'] ?? null,
            'memo' => null,
            'del_flg' => false,
            'member_id' => $payerInfo['member_id'],
        ];
    }

    /**
     * 対象メンバーを追加
     */
    private function addTargetMembers(int $taskId, array $targetMemberIds): void
    {
        foreach ($targetMemberIds as $memberId) {
            // 既存のレコード（論理削除済みも含む）を確認
            $existing = ProjectTaskMember::where('task_id', $taskId)
                ->where('member_id', $memberId)
                ->first();

            if ($existing) {
                // 既存のレコードがある場合は復活
                $existing->update(['del_flg' => false]);
            } else {
                // 存在しない場合は新規作成
                ProjectTaskMember::create([
                    'member_id' => $memberId,
                    'task_id' => $taskId,
                    'del_flg' => false,
                ]);
            }
        }
    }

    /**
     * 対象メンバーを更新
     */
    private function updateTargetMembers(int $taskId, array $targetMemberIds): void
    {
        // 新しいメンバーIDの配列を数値型に統一（比較のため）
        $targetMemberIds = array_map('intval', $targetMemberIds);
        $targetMemberIds = array_unique($targetMemberIds); // 重複を除去

        // 同じmember_idで複数のアクティブなレコードがある場合、最新の1つだけを残して他を論理削除
        $activeRecords = ProjectTaskMember::where('task_id', $taskId)
            ->where('del_flg', false)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('member_id');

        foreach ($activeRecords as $memberId => $records) {
            if ($records->count() > 1) {
                // 最新の1つ以外を論理削除
                $recordsToDelete = $records->skip(1)->pluck('id');
                ProjectTaskMember::whereIn('id', $recordsToDelete)->update(['del_flg' => true]);
            }
        }

        // 既存の全レコード（論理削除済みも含む）を一括取得
        // 同じmember_idで複数のレコードがある場合、最新のもの（IDが大きい）を優先
        $existingRecords = ProjectTaskMember::where('task_id', $taskId)
            ->orderBy('id', 'desc')
            ->get()
            ->unique('member_id')
            ->keyBy('member_id');

        // 既存のアクティブなメンバーIDを取得
        $existingActiveMemberIds = $existingRecords
            ->where('del_flg', false)
            ->keys()
            ->map(fn($id) => (int)$id)
            ->toArray();

        // 削除が必要なメンバー（既存にあって新しいリストにない）
        $toDelete = array_diff($existingActiveMemberIds, $targetMemberIds);
        if (!empty($toDelete)) {
            // 同じmember_idで複数のレコードがある可能性があるため、全て論理削除
            ProjectTaskMember::where('task_id', $taskId)
                ->whereIn('member_id', $toDelete)
                ->where('del_flg', false)
                ->update(['del_flg' => true]);
        }

        // 追加が必要なメンバー（新しいリストにあって既存にない、または論理削除済み）
        $toAdd = array_diff($targetMemberIds, $existingActiveMemberIds);
        if (!empty($toAdd)) {
            // 論理削除済みのレコードを取得（復活用）
            $toRestore = [];
            $toCreate = [];

            foreach ($toAdd as $memberId) {
                $existingRecord = $existingRecords->get($memberId);
                if ($existingRecord) {
                    if ($existingRecord->del_flg) {
                        // 論理削除済みのレコードがある場合は復活対象に追加
                        // unique()で最新の1つだけが取得されているので、その1つだけを復活
                        $toRestore[] = $memberId;
                    }
                    // 既にアクティブなレコードがある場合は何もしない（重複チェック）
                } else {
                    // 存在しない場合は新規作成対象に追加
                    $toCreate[] = $memberId;
                }
            }

            // 論理削除済みレコードを復活
            // 同じmember_idで複数のレコードがある場合、最新の1つだけを復活
            if (!empty($toRestore)) {
                foreach ($toRestore as $memberId) {
                    // 最新の1つだけを取得して復活
                    $recordToRestore = ProjectTaskMember::where('task_id', $taskId)
                        ->where('member_id', $memberId)
                        ->where('del_flg', true)
                        ->orderBy('id', 'desc')
                        ->first();
                    
                    if ($recordToRestore) {
                        // 最新の1つだけを復活
                        $recordToRestore->update(['del_flg' => false]);
                        // 同じmember_idの他の論理削除済みレコードは削除したまま（または物理削除）
                    }
                }
            }

            // 新規レコードを一括作成
            // 作成前に既存レコード（論理削除済みも含む）を再確認して重複を避ける
            if (!empty($toCreate)) {
                // 既に存在するmember_idを除外（論理削除済みも含む）
                $existingAllMemberIds = $existingRecords->keys()->map(fn($id) => (int)$id)->toArray();
                $toCreate = array_diff($toCreate, $existingAllMemberIds);

                if (!empty($toCreate)) {
                    $insertData = array_map(function ($memberId) use ($taskId) {
                        return [
                            'member_id' => $memberId,
                            'task_id' => $taskId,
                            'del_flg' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $toCreate);

                    ProjectTaskMember::insert($insertData);
                }
            }
        }
    }

    /**
     * タスクデータをフォーマット
     */
    private function formatTaskData(ProjectTask $task): array
    {
        // 対象メンバーの名前とIDを取得
        $targetMembers = $task->taskMembers->map(function ($taskMember) {
            $member = $taskMember->projectMember;
            if (!$member || $member->del_flg) return null;
            
            $memberName = $this->getMemberName($member);
            
            return [
                'id' => $member->id,
                'name' => $memberName
            ];
        })->filter()->toArray();
        
        $taskArray = $task->toArray();
        $taskArray['target_members'] = array_column($targetMembers, 'name');
        $taskArray['target_member_ids'] = array_column($targetMembers, 'id');
        
        // 金額を整数として返す（小数点を切り捨て）
        if (isset($taskArray['accounting_amount'])) {
            $taskArray['accounting_amount'] = (int) $taskArray['accounting_amount'];
        }
        
        return $taskArray;
    }
}
