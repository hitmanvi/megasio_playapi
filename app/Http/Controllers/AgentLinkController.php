<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\AgentLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentLinkController extends Controller
{
    /**
     * 根据推广码与 platform（ios|android|web）返回 facebook_config[platform].pixel（供前端埋点）
     */
    public function pixel(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:32',
            'platform' => 'required|string|in:ios,android,web',
        ]);

        $code = $request->input('code');
        $platform = $request->input('platform', 'web');
        $link = AgentLink::findByPromotionCode($code);
        if (! $link) {
            return $this->error(ErrorCode::NOT_FOUND, 'Invalid or inactive promotion code');
        }

        $pixelId = $this->resolvePixelId($link, $platform);

        return $this->responseItem([
            'promotion_code' => $link->promotion_code,
            'platform' => $platform,
            'pixel_id' => $pixelId,
        ]);
    }

    /**
     * facebook_config.enabled === false 时不返回 pixel；否则取对应平台节点下的 pixel 字段
     */
    protected function resolvePixelId(AgentLink $link, string $platform): ?string
    {
        $cfg = $link->getFacebookConfig();
        if (isset($cfg['enabled']) && ! $cfg['enabled']) {
            return null;
        }
        $id = $cfg[$platform]['pixel'] ?? null;

        return ($id !== null && $id !== '') ? (string) $id : null;
    }
}
