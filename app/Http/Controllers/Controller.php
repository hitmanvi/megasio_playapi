<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use App\Traits\ResponseTrait;

class Controller
{
    use AuthorizesRequests, ValidatesRequests, ResponseTrait;
}
