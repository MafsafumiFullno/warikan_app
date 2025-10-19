<?php

namespace App\Http\Controllers;

use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class ProjectController extends Controller
{
    protected ProjectService $projectService;
    protected LoggerInterface $logger;

    public function __construct(
        ProjectService $projectService,
        LoggerInterface $logger
    ) {
        $this->projectService = $projectService;
        $this->logger = $logger;
    }

    /**
     * 一覧取得（ログイン中の顧客のプロジェクトのみ）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $filters = $request->only(['project_status', 'q', 'per_page']);
            
            $result = $this->projectService->getProjectsForCustomer($customer->customer_id, $filters);

            return response()->json($result);
        } catch (\Exception $e) {
            $this->logger->error('プロジェクト一覧取得エラー: ' . $e->getMessage(), [
                'customer_id' => $request->user()?->customer_id,
                'request' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'プロジェクト一覧の取得に失敗しました。',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * プロジェクト作成
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectService->createProject($customer->customer_id, $request->all());

            return response()->json($result, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('プロジェクト作成エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => 'プロジェクトの作成に失敗しました。',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * プロジェクト詳細取得
     */
    public function show(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectService->getProjectWithAccessCheck($customer->customer_id, $projectId);

            return response()->json(['project' => $result['project']]);
        } catch (\Exception $e) {
            $this->logger->error('プロジェクト詳細取得エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], $e->getMessage() === 'プロジェクトが見つかりません。' ? 404 : 
               ($e->getMessage() === 'アクセス権限がありません。' ? 403 : 500));
        }
    }

    /**
     * プロジェクト更新
     */
    public function update(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectService->updateProject($customer->customer_id, $projectId, $request->all());

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('プロジェクト更新エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'customer_id' => $request->user()?->customer_id,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], $e->getMessage() === 'プロジェクトが見つかりません。' ? 404 : 
               ($e->getMessage() === '無効な割り勘方法IDです。' ? 422 : 500));
        }
    }

    /**
     * プロジェクト論理削除
     */
    public function destroy(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectService->deleteProject($customer->customer_id, $projectId);

            return response()->json($result);
        } catch (\Exception $e) {
            $this->logger->error('プロジェクト削除エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], $e->getMessage() === 'プロジェクトが見つかりません。' ? 404 : 500);
        }
    }

}
