<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

abstract class BaseService
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
     * データをバリデーション
     */
    protected function validateData(array $data, array $rules): array
    {
        $validator = $this->validationFactory->make($data, $rules);

        if ($validator->fails()) {
            $this->logError('バリデーションエラー', ['errors' => $validator->errors()]);
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /**
     * 情報ログを出力
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * エラーログを出力
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * データベーストランザクションを開始
     */
    protected function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * データベーストランザクションをコミット
     */
    protected function commitTransaction(): void
    {
        $this->db->commit();
    }

    /**
     * データベーストランザクションをロールバック
     */
    protected function rollbackTransaction(): void
    {
        $this->db->rollBack();
    }

    /**
     * トランザクション内で処理を実行
     */
    protected function executeInTransaction(callable $callback)
    {
        $this->beginTransaction();
        
        try {
            $result = $callback();
            $this->commitTransaction();
            return $result;
        } catch (\Exception $e) {
            $this->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * プロジェクトのアクセス権限をチェック
     */
    protected function validateProjectAccess($customerId, int $projectId): Project
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

        return $project;
    }

    /**
     * プロジェクトのオーナー権限をチェック
     */
    protected function validateOwnerAccess($customerId, int $projectId): Project
    {
        $project = Project::where('project_id', $projectId)
            ->where('del_flg', false)
            ->first();
        
        if (!$project) {
            throw new \Exception('プロジェクトが見つかりません');
        }

        // オーナーロールをチェック
        $ownerRole = ProjectRole::where('role_code', 'owner')->first();
        if (!$ownerRole) {
            throw new \Exception('オーナーロールが見つかりません');
        }

        $isOwner = ProjectMember::where('project_id', $projectId)
                               ->where('customer_id', $customerId)
                               ->where('role_id', $ownerRole->role_id)
                               ->where('del_flg', false)
                               ->exists();
        
        if (!$isOwner && $project->customer_id !== $customerId) {
            throw new \Exception('オーナー権限がありません');
        }

        return $project;
    }

    /**
     * レスポンス形式を統一
     */
    protected function formatResponse(string $message, $data = null, bool $success = true): array
    {
        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * 成功レスポンス
     */
    protected function successResponse(string $message, $data = null): array
    {
        return $this->formatResponse($message, $data, true);
    }

    /**
     * エラーレスポンス
     */
    protected function errorResponse(string $message, $data = null): array
    {
        return $this->formatResponse($message, $data, false);
    }

    /**
     * プロジェクトメンバーを取得
     */
    protected function getProjectMember(int $projectId, int $memberId): ProjectMember
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
    protected function getProjectMemberByProjectMemberId(int $projectId, int $memberId): ProjectMember
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
     * メンバー名を取得
     */
    protected function getMemberName($member): string
    {
        if ($member->customer_id) {
            return $member->customer->nick_name ?: 
                   trim($member->customer->first_name . ' ' . $member->customer->last_name) ?: 
                   'メンバー';
        }
        
        return $member->member_name ?: 'メンバー';
    }

    /**
     * オーナー名を取得
     */
    protected function getOwnerName(Project $project): string
    {
        $owner = $project->customer;
        return trim($owner->first_name . ' ' . $owner->last_name) ?: 
               $owner->nick_name ?: 
               'オーナー';
    }

    /**
     * 論理削除フラグを設定
     */
    protected function softDelete($model): void
    {
        $model->update(['del_flg' => true]);
    }

    /**
     * 配列からnull値を除去
     */
    protected function removeNullValues(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }

    /**
     * 数値を指定の小数点以下で丸める
     */
    protected function roundAmount(float $amount, int $decimals = 2): float
    {
        return round($amount, $decimals);
    }

    /**
     * 配列の値を指定の小数点以下で丸める
     */
    protected function roundAmounts(array $amounts, int $decimals = 2): array
    {
        return array_map(function ($amount) use ($decimals) {
            return is_numeric($amount) ? round($amount, $decimals) : $amount;
        }, $amounts);
    }
}
