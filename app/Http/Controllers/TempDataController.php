<?php

namespace App\Http\Controllers;

use App\Models\TempData;
use Illuminate\Http\Request;

class TempDataController extends Controller
{
    /**
     * 接收并保存数据
     */
    public function store(Request $request)
    {
        $payload = $request->all();
        if (empty($payload)) {
            $payload = ['raw' => $request->getContent() ?: null];
        }

        $tempData = TempData::create(['payload' => $payload]);

        return $this->responseItem([
            'id' => $tempData->id,
            'created_at' => $tempData->created_at->toIso8601String(),
        ]);
    }

    /**
     * 列表展示接收到的数据
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $items = TempData::orderByDesc('id')->paginate($perPage)->through(fn ($item) => [
            'id' => $item->id,
            'payload' => $item->payload,
            'created_at' => $item->created_at->toIso8601String(),
        ]);

        return $this->responseListWithPaginator($items);
    }
}
