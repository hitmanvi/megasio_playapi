<?php

/**
 * User: hitman
 * Date: 2019/9/12
 * Time: 3:07 PM
 */

namespace App\Traits;

use App\Enums\ErrorCode;
use ArrayObject;
use Illuminate\Support\Arr;

trait ResponseTrait
{
    protected function responseItem($data)
    {
        $resp = [
            'code'   => ErrorCode::SUCCESS->value,
            'errmsg' => ErrorCode::SUCCESS->getMessage(),
            'data'   => $data
        ];

        return response()->json($resp);
    }

    /**
     * 错误响应
     * 
     * @param ErrorCode $errorCode ErrorCode枚举
     * @param mixed $data 额外数据
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(ErrorCode $errorCode, $data = null)
    {
        $resp = [
            'code'   => $errorCode->value,
            'errmsg' => $errorCode->getMessage(),
            'data'   => $data
        ];

        return response()->json($resp);
    }

    public function responseList($listItem, $meta=[])
    {
        $data           = [];
        if (count($meta) == 0) {
            $meta = new ArrayObject();
        }
        $data['code']   = ErrorCode::SUCCESS->value;
        $data['errmsg'] = ErrorCode::SUCCESS->getMessage();
        $data['data']   = [
            'items' => $listItem,
            'meta' => $meta,
        ];

        return response()->json($data);
    }

    public function responseListWithPaginator($listItem, $extra = null)
    {
        $data['code']          = ErrorCode::SUCCESS->value;
        $data['errmsg']        = ErrorCode::SUCCESS->getMessage();
        $data['data']['items'] = $listItem->getCollection();
        $data['meta']          = Arr::only($listItem->toArray(), [
            'current_page',
            'last_page',
            'total',
        ]);
        $data['data']['meta'] = $data['meta'];
        $data['extra']         = $extra;

        return response()->json($data);
    }
}
