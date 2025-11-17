<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    protected InvitationService $invitationService;

    public function __construct(InvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * 获取邀请统计数据
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $this->invitationService->getInvitationStats($user->id);

        return $this->responseItem($stats);
    }

    /**
     * 获取邀请列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $invitationsPaginator = $this->invitationService->getInvitationListPaginated($user->id, $perPage);

        // 格式化分页数据
        $invitations = $invitationsPaginator->getCollection();
        $result = $invitations->map(function ($invitation) {
            return $this->invitationService->formatInvitationItem($invitation);
        });

        // 创建新的分页器，使用格式化后的数据
        $formattedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $result,
            $invitationsPaginator->total(),
            $invitationsPaginator->perPage(),
            $invitationsPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }
}
