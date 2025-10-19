<?php

namespace App\Services;

use App\Models\CustomerSplitMethod;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

class ProjectService
{
    protected ValidationFactory $validationFactory;
    protected LoggerInterface $logger;

    public function __construct(
        ValidationFactory $validationFactory,
        LoggerInterface $logger
    ) {
        $this->validationFactory = $validationFactory;
        $this->logger = $logger;
    }

    /**
     * プロジェクト一覧を取得
     */
    public function getProjectsForCustomer($customerId, array $filters = []): array
    {
        $query = Project::query()
            ->where('customer_id', $customerId)
            ->where('del_flg', false)
            ->orderByDesc('created_at');

        // ステータスフィルター
        if (isset($filters['project_status'])) {
            $query->where('project_status', $filters['project_status']);
        }

        // キーワード検索
        if (isset($filters['q'])) {
            $keyword = $filters['q'];
            $query->where(function ($q) use ($keyword) {
                $q->where('project_name', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // ページネーション（デフォルト20件）
        $perPage = $filters['per_page'] ?? 20;
        $projects = $query->paginate($perPage);

        return [
            'projects' => $projects->items(),
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ]
        ];
    }

    /**
     * プロジェクトを作成
     */
    public function createProject($customerId, array $data): array
    {
        $this->logger->info('プロジェクト作成開始', [
            'customer_id' => $customerId,
            'request_data' => $data
        ]);

        // バリデーション
        $validator = $this->validationFactory->make($data, [
            'project_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_status' => ['nullable', 'string', Rule::in(['draft', 'active', 'completed', 'archived'])],
            'split_method_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $this->logger->info('バリデーション完了', ['validated_data' => $validated]);

        // 割り勘方法の存在確認
        if (isset($validated['split_method_id'])) {
            $this->validateSplitMethod($customerId, $validated['split_method_id']);
        }

        // プロジェクト作成
        $project = Project::create([
            'customer_id' => $customerId,
            'project_name' => $validated['project_name'],
            'description' => $validated['description'] ?? null,
            'project_status' => $validated['project_status'] ?? 'draft',
            'split_method_id' => $validated['split_method_id'] ?? null,
            'del_flg' => false,
        ]);

        // プロジェクト作成者をオーナーとしてメンバーに追加
        $this->addOwnerAsMember($project);

        $this->logger->info('プロジェクト作成完了', ['project_id' => $project->project_id]);

        return [
            'project' => $project
        ];
    }

    /**
     * プロジェクトを取得（アクセス権限チェック付き）
     */
    public function getProjectWithAccessCheck($customerId, int $projectId): array
    {
        // プロジェクトの存在確認
        $project = Project::where('project_id', $projectId)
            ->where('del_flg', false)
            ->first();
        
        if (!$project) {
            throw new \Exception('プロジェクトが見つかりません。');
        }

        // プロジェクトのオーナーまたはメンバーかチェック
        $isOwner = $project->customer_id === $customerId;
        $isMember = ProjectMember::where('project_id', $projectId)
                                ->where('customer_id', $customerId)
                                ->where('del_flg', false)
                                ->exists();
        
        if (!$isOwner && !$isMember) {
            throw new \Exception('アクセス権限がありません。');
        }

        return [
            'project' => $project,
            'isOwner' => $isOwner,
            'isMember' => $isMember
        ];
    }

    /**
     * プロジェクトを更新
     */
    public function updateProject($customerId, int $projectId, array $data): array
    {
        // プロジェクトの存在確認とオーナー権限チェック
        $project = Project::where('project_id', $projectId)
            ->where('customer_id', $customerId)
            ->where('del_flg', false)
            ->first();

        if (!$project) {
            throw new \Exception('プロジェクトが見つかりません。');
        }

        // バリデーション
        $validator = $this->validationFactory->make($data, [
            'project_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_status' => ['sometimes', 'required', 'string', Rule::in(['draft', 'active', 'completed', 'archived'])],
            'split_method_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        // 割り勘方法の存在確認
        if (array_key_exists('split_method_id', $validated) && !is_null($validated['split_method_id'])) {
            $this->validateSplitMethod($customerId, $validated['split_method_id']);
        }

        // プロジェクト更新
        $project->fill($validated);
        $project->save();

        return [
            'project' => $project
        ];
    }

    /**
     * プロジェクトを論理削除
     */
    public function deleteProject($customerId, int $projectId): array
    {
        $project = Project::where('project_id', $projectId)
            ->where('customer_id', $customerId)
            ->where('del_flg', false)
            ->first();

        if (!$project) {
            throw new \Exception('プロジェクトが見つかりません。');
        }

        $project->del_flg = true;
        $project->save();

        return [
            'message' => '削除しました。'
        ];
    }

    /**
     * 割り勘方法の存在確認
     */
    private function validateSplitMethod($customerId, int $splitMethodId): void
    {
        $exists = CustomerSplitMethod::where('split_method_id', $splitMethodId)
            ->where('customer_id', $customerId)
            ->exists();
        
        if (!$exists) {
            throw new \Exception('無効な割り勘方法IDです。');
        }
    }

    /**
     * プロジェクト作成者をオーナーとしてメンバーに追加
     */
    private function addOwnerAsMember(Project $project): void
    {
        $ownerRole = ProjectRole::where('role_code', 'owner')->first();
        if (!$ownerRole) {
            throw new \Exception('オーナーロールが見つかりません');
        }

        ProjectMember::create([
            'project_id' => $project->project_id,
            'project_member_id' => 1, // オーナーは常に1番
            'customer_id' => $project->customer_id,
            'role_id' => $ownerRole->role_id,
            'split_weight' => 1.00,
            'del_flg' => false,
        ]);
    }
}
