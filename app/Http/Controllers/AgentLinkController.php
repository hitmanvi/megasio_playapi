<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\AgentLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentLinkController extends Controller
{
    /**
     * 根据推广码返回该 AgentLink 配置的 Facebook Pixel ID（供前端埋点）
     */
    public function pixel(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $code = $request->input('code');
        $link = AgentLink::findByPromotionCode($code);
        if (! $link) {
            return $this->error(ErrorCode::NOT_FOUND, 'Invalid or inactive promotion code');
        }

        $pixelId = $this->resolvePixelId($link);

        return $this->responseItem([
            'promotion_code' => $link->promotion_code,
            'pixel_id' => $pixelId,
        ]);
    }

    /**
     * facebook_config.enabled === false 时不返回 pixel；否则取 pixel_id（字符串，可能为空）
     */
    protected function resolvePixelId(AgentLink $link): ?string
    {
        $cfg = $link->getFacebookConfig();
        if (isset($cfg['enabled']) && ! $cfg['enabled']) {
            return null;
        }
        $id = $cfg['pixel'] ?? null;

        return ($id !== null && $id !== '') ? (string) $id : null;
    }
}
