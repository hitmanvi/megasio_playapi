<?php

namespace App\Http\Controllers;

use App\Models\SiteLink;
use Illuminate\Http\JsonResponse;

class SiteLinkController extends Controller
{
    /**
     * 站点链接列表：仅返回 enabled 为 true 的项（key、url、deletable）
     */
    public function index(): JsonResponse
    {
        $items = SiteLink::query()
            ->where('enabled', true)
            ->orderBy('key')
            ->get()
            ->map(fn (SiteLink $link) => [
                'key' => $link->key,
                'url' => $link->url,
                'deletable' => $link->deletable,
            ])
            ->values()
            ->all();

        return $this->responseList($items);
    }
}
