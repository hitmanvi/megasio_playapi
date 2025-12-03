<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class InsufficientBalanceException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct(ErrorCode::INSUFFICIENT_BALANCE);
        
        if ($message) {
            $this->message = $message;
        }
    }
}

