<?php

namespace App\Http\Controllers;

class UtilsController extends Controller
{
    public function timestamp()
    {
        return $this->responseItem([
            'timestamp' => time(),
        ]);
    }

    public function settings()
    {
        return $this->responseItem([
            'currency' => 'USD',
        ]);
    }
}