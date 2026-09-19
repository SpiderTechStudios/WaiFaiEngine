<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestWifiDogController extends Controller
{
    public function login(Request $request)
    {
        Log::info("call from Login", ['request' => $request->all()]);
    }
    public function auth(Request $request)
    {
        Log::info("call from Auth", ['request' => $request->all()]);
    }
    public function portal(Request $request)
    {
        Log::info("call from Portal", ['request' => $request->all()]);
    }
    public function ping(Request $request)
    {
        Log::info("call from ping", ['request' => $request->all()]);
    }
}
