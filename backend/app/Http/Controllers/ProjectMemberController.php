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
            return $this->handleException($e, $request, $this->logger, 'メンバー一覧取得');
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
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, 'メンバー追加');
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
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, 'メモ更新');
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
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, '比重更新');
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
            return $this->handleException($e, $request, $this->logger, 'メンバー削除');
        }
    }
}
