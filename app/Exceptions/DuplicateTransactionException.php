<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class DuplicateTransactionException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct(ErrorCode::BET_DUP);
        
        if ($message) {
            $this->message = $message;
        }
    }
}

