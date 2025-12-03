<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class InvalidTokenException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct(ErrorCode::TOKEN_INVALID);
        
        if ($message) {
            $this->message = $message;
        }
    }
}

