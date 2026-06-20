<?php

namespace App\Services\Project;

use App\Models\ProjectMember;
use App\Models\ProjectShareLink;
use App\Models\ProjectTask;
use App\Models\ProjectTaskMember;
use App\Services\BaseService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ProjectShareLinkService extends BaseService
{
    private const MAX_TOKEN_RETRY = 5;
    private const SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION = '23000';
    private const SQLSTATE_UNIQUE_VIOLATION = '23505';

    /**
     * 共有リンクを作成（既存があれば再利用）
     */
    public function createOrGetShareLink(int $customerId, int $projectId): array
    {
        $project = $this->validateOwnerAccess($customerId, $projectId);

        $existingLink = ProjectShareLink::where('project_id', $project->project_id)
            ->where('del_flg', false)
            ->first();

        if ($existingLink) {
            return ['share_link' => $this->formatShareLinkResponse($existingLink)];
        }

        $shareLink = $this->createShareLinkWithRetry($project->project_id, $customerId);

        return ['share_link' => $this->formatShareLinkResponse($shareLink)];
    }

    /**
     * 共有トークンから公開用プロジェクト詳細を取得
     */
    public function getPublicProjectByToken(string $token): array
    {
        $tokenHash = hash('sha256', $token);

        $shareLink = ProjectShareLink::where('token_hash', $tokenHash)
            ->where('del_flg', false)
            ->first();

        if (!$shareLink) {
            throw new \Exception('共有リンクが見つかりません');
        }

        $project = $shareLink->project()
            ->where('del_flg', false)
            ->first();

        if (!$project) {
            throw new \Exception('共有リンクが見つかりません');
        }

        $members = ProjectMember::where('project_id', $project->project_id)
            ->where('del_flg', false)
            ->with(['customer'])
            ->get()
            ->map(function (ProjectMember $member) {
                $memberName = $this->getMemberName($member);
                $totalExpense = ProjectTask::where('project_id', $member->project_id)
                    ->where('task_member_name', $memberName)
                    ->where('accounting_type', 'expense')
                    ->where('del_flg', false)
                    ->sum('accounting_amount');

                return [
                    'project_member_id' => $member->project_member_id,
                    'name' => $memberName,
                    'split_weight' => (float) $member->split_weight,
                    'total_expense' => $this->roundAmount((float) $totalExpense),
                ];
            })
            ->values()
            ->all();

        $accountings = ProjectTask::where('project_id', $project->project_id)
            ->where('del_flg', false)
            ->with(['taskMembers' => function ($query) {
                $query->where('del_flg', false);
            }, 'taskMembers.projectMember.customer'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (ProjectTask $task) {
                $targetMembers = $task->taskMembers->map(function ($taskMember) {
                    $member = $taskMember->projectMember;
                    return $member ? $this->getMemberName($member) : null;
                })->filter()->values()->all();

                return [
                    'task_name' => $task->task_name,
                    'accounting_amount' => (int) $task->accounting_amount,
                    'accounting_type' => $task->accounting_type,
                    'target_members' => $targetMembers,
                    'payer_name' => $task->task_member_name,
                ];
            })
            ->values()
            ->all();

        return [
            'project' => [
                'project_id' => $project->project_id,
                'project_name' => $project->project_name,
                'description' => $project->description,
                'project_status' => $project->project_status,
                'created_at' => $project->created_at,
                'updated_at' => $this->resolveProjectUpdatedAt($project->project_id, $project->updated_at),
                'members' => $members,
                'accountings' => $accountings,
            ],
            'capabilities' => [
                'can_edit' => false,
            ],
        ];
    }

    /**
     * 共有リンクAPIレスポンス用に整形
     */
    private function formatShareLinkResponse(ProjectShareLink $shareLink): array
    {
        $token = Crypt::decryptString($shareLink->token_encrypted);

        return [
            'project_id' => $shareLink->project_id,
            'token' => $token,
            'share_url' => $this->buildShareUrl($token),
            'permission' => $shareLink->permission,
            'created_at' => $shareLink->created_at,
        ];
    }

    /**
     * 共有URLを構築
     */
    private function buildShareUrl(string $token): string
    {
        $frontendUrl = config('share_link.frontend_base_url');
        return rtrim($frontendUrl, '/') . '/share/' . $token;
    }

    /**
     * トークン衝突時に再生成しながら共有リンクを作成
     */
    private function createShareLinkWithRetry(int $projectId, int $customerId): ProjectShareLink
    {
        for ($attempt = 0; $attempt < self::MAX_TOKEN_RETRY; $attempt++) {
            $rawToken = Str::random(64);
            $tokenHash = hash('sha256', $rawToken);

            try {
                return ProjectShareLink::create([
                    'project_id' => $projectId,
                    'token_hash' => $tokenHash,
                    'token_encrypted' => Crypt::encryptString($rawToken),
                    'permission' => 'owner_only',
                    'created_by_customer_id' => $customerId,
                    'del_flg' => false,
                ]);
            } catch (QueryException $e) {
                if (!$this->isUniqueConstraintViolation($e)) {
                    throw $e;
                }

                $existingLink = ProjectShareLink::where('project_id', $projectId)
                    ->where('del_flg', false)
                    ->first();

                if ($existingLink) {
                    return $existingLink;
                }

                if ($attempt === self::MAX_TOKEN_RETRY - 1) {
                    throw new \Exception('共有リンクの作成に失敗しました。しばらくしてから再試行してください。');
                }
            }
        }

        throw new \Exception('共有リンクの作成に失敗しました。しばらくしてから再試行してください。');
    }

    /**
     * 一意制約違反かどうかを判定
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        if (
            $sqlState === self::SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION
            || $sqlState === self::SQLSTATE_UNIQUE_VIOLATION
        ) {
            return true;
        }

        $message = $e->getMessage();
        return str_contains($message, 'UNIQUE constraint failed') || str_contains($message, 'Duplicate entry');
    }

    /**
     * プロジェクト配下の更新を集約した表示用更新日時を返す
     */
    private function resolveProjectUpdatedAt(int $projectId, $projectUpdatedAt): Carbon
    {
        $memberUpdatedAt = ProjectMember::where('project_id', $projectId)->max('updated_at');
        $taskUpdatedAt = ProjectTask::where('project_id', $projectId)->max('updated_at');
        $taskMemberUpdatedAt = ProjectTaskMember::whereHas('projectTask', function ($query) use ($projectId) {
            $query->where('project_id', $projectId);
        })->max('updated_at');

        return collect([$projectUpdatedAt, $memberUpdatedAt, $taskUpdatedAt, $taskMemberUpdatedAt])
            ->filter()
            ->map(function ($value) {
                return $value instanceof Carbon ? $value : Carbon::parse($value);
            })
            ->sortDesc()
            ->first();
    }
}
