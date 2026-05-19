<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SwaggerController extends Controller
{
    public function index()
    {
        return view('swagger-ui');
    }
}
