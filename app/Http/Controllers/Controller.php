<?php

namespace App\Http\Controllers;
use App\Traits\RespondWithHttpStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    //
    use RespondWithHttpStatus, AuthorizesRequests, ValidatesRequests;
}
