<?php

namespace App\Http\Controllers;

use App\Models\SiteLink;
use Illuminate\Http\JsonResponse;

class SiteLinkController extends Controller
{
    /**
     * 站点链接列表（key、url、是否可删除）
     */
    public function index(): JsonResponse
    {
        $items = SiteLink::query()
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
