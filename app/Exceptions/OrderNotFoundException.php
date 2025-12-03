<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class OrderNotFoundException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct(ErrorCode::NOT_FOUND);
        
        if ($message) {
            $this->message = $message;
        }
    }
}

