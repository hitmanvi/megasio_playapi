<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Http\Request;

class Exception extends \Exception
{
    protected ErrorCode $errorCode;

    public function __construct(ErrorCode $code, ?string $message = null)
    {
        $this->errorCode = $code;
        parent::__construct($message ?? $code->getMessage());
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    public function render(Request $request)
    {
        return response()->json([
            'code'   => $this->errorCode->value,
            'errmsg' => $this->errorCode->getMessage(),
            'data'   => null,
        ]);
    }
}