<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class GameNotEnabledException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct(ErrorCode::OPERATION_NOT_ALLOWED);
        
        if ($message) {
            $this->message = $message;
        }
    }
}

