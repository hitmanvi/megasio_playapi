<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    /**
     * 获取品牌列表
     */
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query()
            ->enabled()
            ->ordered()
            ->paginate($request->input('per_page', 10));

        return $this->responseListWithPaginator($brands);
    }
}
