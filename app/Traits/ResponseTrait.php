<?php

/**
 * User: hitman
 * Date: 2019/9/12
 * Time: 3:07 PM
 */

namespace App\Traits;

use ArrayObject;
use Illuminate\Support\Arr;

trait ResponseTrait
{
    protected function responseItem($data)
    {
        $resp = [
            'code'   => 0,
            'errmsg' => '',
            'data'   => $data
        ];

        return response()->json($resp);
    }

    protected function error($err, $data = null)
    {
        $resp = [
            'code'   => $err[0],
            'errmsg' => $err[1],
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
        $data['code']   = 0;
        $data['errmsg'] = '';
        $data['data']   = [
            'items' => $listItem,
            'meta' => $meta,
        ];

        return response()->json($data);
    }

    public function responseListWithPaginator($listItem, $extra = null)
    {
        $data['code']          = 0;
        $data['errmsg']        = '';
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
