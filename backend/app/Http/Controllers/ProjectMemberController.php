<?php

namespace App\Http\Controllers;

use App\Services\Project\ProjectMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class ProjectMemberController extends Controller
{
    protected ProjectMemberService $projectMemberService;
    protected LoggerInterface $logger;

    public function __construct(
        ProjectMemberService $projectMemberService,
        LoggerInterface $logger
    ) {
        $this->projectMemberService = $projectMemberService;
        $this->logger = $logger;
    }
    /**
     * プロジェクトのメンバー一覧を取得
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectMemberService->getProjectMembers($customer->customer_id, $projectId);

            return response()->json($result);
        } catch (\Exception $e) {
            $this->logger->error('メンバー一覧取得エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], $e->getMessage() === 'プロジェクトが見つかりません' ? 404 : 
               ($e->getMessage() === 'アクセス権限がありません' ? 403 : 500));
        }
    }

    /**
     * プロジェクトにメンバーを追加
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectMemberService->addProjectMember($customer->customer_id, $projectId, $request->all());

            return response()->json($result, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('メンバー追加エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'customer_id' => $request->user()?->customer_id,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], $e->getMessage() === 'プロジェクトが見つかりません' ? 404 : 
               ($e->getMessage() === 'オーナー権限がありません' ? 403 : 
               ($e->getMessage() === 'このメンバーは既に追加されています' ? 409 : 500)));
        }
    }

    /**
     * メンバーのメモを更新
     */
    public function updateMemo(Request $request, int $projectId, int $memberId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectMemberService->updateMemberMemo($customer->customer_id, $projectId, $memberId, $request->all());

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('メモ更新エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'member_id' => $memberId,
                'customer_id' => $request->user()?->customer_id,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], $e->getMessage() === 'オーナー権限がありません' ? 403 : 
               ($e->getMessage() === 'メンバーが見つかりません' ? 404 : 500));
        }
    }

    /**
     * メンバーの比重を更新
     */
    public function updateWeight(Request $request, int $projectId, int $memberId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectMemberService->updateMemberWeight($customer->customer_id, $projectId, $memberId, $request->all());

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('比重更新エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'member_id' => $memberId,
                'customer_id' => $request->user()?->customer_id,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], $e->getMessage() === 'オーナー権限がありません' ? 403 : 
               ($e->getMessage() === 'メンバーが見つかりません' ? 404 : 500));
        }
    }

    /**
     * プロジェクトからメンバーを削除
     */
    public function destroy(Request $request, int $projectId, int $memberId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectMemberService->removeProjectMember($customer->customer_id, $projectId, $memberId);

            return response()->json($result);
        } catch (\Exception $e) {
            $this->logger->error('メンバー削除エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'member_id' => $memberId,
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], $e->getMessage() === 'オーナー権限がありません' ? 403 : 
               ($e->getMessage() === 'メンバーが見つかりません' ? 404 : 500));
        }
    }
}
