<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Http\Request;

class Exception extends \Exception
{
    public function __construct(ErrorCode $code)
    {
        $this->code = $code;
        parent::__construct($code->getMessage());
    }

    public function render(Request $request)
    {
        return response()->json([
            'code'   => $this->code->value,
            'errmsg' => $this->code->getMessage(),
            'data'   => null,
        ]);
    }
}