<?php

namespace App\Http\Controllers;

use App\Models\CustomerSplitMethod;
use App\Models\Project;
use App\Services\Split\EqualSplitStrategy;
use App\Services\Split\SplitService;
use App\Services\Split\WeightedSplitStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /**
     * 一覧取得（ログイン中の顧客のプロジェクトのみ）
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $query = Project::query()
            ->where('customer_id', $customer->customer_id)
            ->where('del_flg', false)
            ->orderByDesc('created_at');

        if ($request->filled('project_status')) {
            $query->where('project_status', $request->string('project_status'));
        }

        if ($request->filled('q')) {
            $keyword = $request->string('q');
            $query->where(function ($q) use ($keyword) {
                $q->where('project_name', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        $projects = $query->get();

        return response()->json(['projects' => $projects]);
    }

    /**
     * プロジェクト作成
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();

        $validated = $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_status' => ['nullable', 'string', Rule::in(['draft', 'active', 'completed', 'archived'])],
            'split_method_id' => ['nullable', 'integer'],
        ]);

        if (isset($validated['split_method_id'])) {
            $exists = CustomerSplitMethod::where('split_method_id', $validated['split_method_id'])
                ->where('customer_id', $customer->customer_id)
                ->exists();
            if (!$exists) {
                return response()->json([
                    'message' => '無効な割り勘方法IDです。'
                ], 422);
            }
        }

        $project = Project::create([
            'customer_id' => $customer->customer_id,
            'project_name' => $validated['project_name'],
            'description' => $validated['description'] ?? null,
            'project_status' => $validated['project_status'] ?? 'draft',
            'split_method_id' => $validated['split_method_id'] ?? null,
            'del_flg' => false,
        ]);

        return response()->json(['project' => $project], 201);
    }

    /**
     * プロジェクト詳細取得
     */
    public function show(Request $request, int $projectId): JsonResponse
    {
        $customer = $request->user();

        $project = Project::where('project_id', $projectId)
            ->where('customer_id', $customer->customer_id)
            ->where('del_flg', false)
            ->first();

        if (!$project) {
            return response()->json(['message' => 'プロジェクトが見つかりません。'], 404);
        }

        return response()->json(['project' => $project]);
    }

    /**
     * プロジェクト更新
     */
    public function update(Request $request, int $projectId): JsonResponse
    {
        $customer = $request->user();

        $project = Project::where('project_id', $projectId)
            ->where('customer_id', $customer->customer_id)
            ->where('del_flg', false)
            ->first();

        if (!$project) {
            return response()->json(['message' => 'プロジェクトが見つかりません。'], 404);
        }

        $validated = $request->validate([
            'project_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_status' => ['sometimes', 'required', 'string', Rule::in(['draft', 'active', 'completed', 'archived'])],
            'split_method_id' => ['nullable', 'integer'],
        ]);

        if (array_key_exists('split_method_id', $validated) && !is_null($validated['split_method_id'])) {
            $exists = CustomerSplitMethod::where('split_method_id', $validated['split_method_id'])
                ->where('customer_id', $customer->customer_id)
                ->exists();
            if (!$exists) {
                return response()->json([
                    'message' => '無効な割り勘方法IDです。'
                ], 422);
            }
        }

        $project->fill($validated);
        $project->save();

        return response()->json(['project' => $project]);
    }

    /**
     * プロジェクト論理削除
     */
    public function destroy(Request $request, int $projectId): JsonResponse
    {
        $customer = $request->user();

        $project = Project::where('project_id', $projectId)
            ->where('customer_id', $customer->customer_id)
            ->where('del_flg', false)
            ->first();

        if (!$project) {
            return response()->json(['message' => 'プロジェクトが見つかりません。'], 404);
        }

        $project->del_flg = true;
        $project->save();

        return response()->json(['message' => '削除しました。']);
    }

    /**
     * 精算計算
     * - split_type: 'equal' | 'weighted'
     * - weights: { customer_id: weight }
     */
    public function settlement(Request $request, int $projectId): JsonResponse
    {
        $customer = $request->user();

        $project = Project::where('project_id', $projectId)
            ->where('customer_id', $customer->customer_id)
            ->where('del_flg', false)
            ->first();

        if (!$project) {
            return response()->json(['message' => 'プロジェクトが見つかりません。'], 404);
        }

        $validated = $request->validate([
            'split_type' => ['required', Rule::in(['equal', 'weighted'])],
            'weights' => ['nullable', 'array'],
            'weights.*' => ['numeric', 'min:0'],
        ]);

        $service = new SplitService();

        if ($validated['split_type'] === 'equal') {
            $strategy = new EqualSplitStrategy();
            $result = $service->calculateForProject($projectId, $strategy);
        } else {
            $strategy = new WeightedSplitStrategy();
            // weights は customer_id をキーにした配列を想定
            $weights = [];
            foreach (($validated['weights'] ?? []) as $key => $value) {
                $weights[(int) $key] = (float) $value;
            }
            $result = $service->calculateForProject($projectId, $strategy, ['weights' => $weights]);
        }

        return response()->json($result);
    }
}


