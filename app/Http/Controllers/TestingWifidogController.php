<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TestingWifidogController extends Controller
{

    protected function createToken(string $ip, string $mac): string
    {
        $ip = str_replace('.', '', trim($ip));
        $mac = str_replace(':', '', strtolower(trim($mac)));

        // Combine and encode using native PHP Base64
        return base64_encode($ip . '|' . $mac);
    }




    public function login(Request $request)
    {
        Log::info('WIFIDOG_LOGIN : REQUEST', $request->all());

        $gwAddress = $request->gw_address;
        $gwPort = $request->gw_port;
        $ip = $request->ip;
        $mac = $request->mac;

        $token = $this->createToken($ip, $mac);
        

        $url = 'http://' . $gwAddress . ':' . $gwPort . '/wifidog/auth?token=' . $token;

        return redirect()->away($url);
    }


    public function auth(Request $request)
    {
        Log::info('WIFIDOG_AUTH_IN : REQUEST ', $request->all());
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'stage' => 'required|string|in:login,logout,counters',
            'incoming' => 'required_if:stage,counters|integer',
            'outgoing' => 'required_if:stage,counters|integer',
            'ip' => 'ip',
            'mac' => 'string',
        ]);
        $userStatus = -1;
        $status = 400;
        if (!$validator->fails()) {
            $userStatus = 1;
            $status = 200;
        }
        return response()->txt('Auth: ' . $userStatus, $status);
    }

    public function portal(Request $request)
    {
        Log::info('WIFIDOG_PORTAL_IN : REQUEST ', $request->all());
        return response()->json([
            'message' => 'Portal endpoint reached',
            'data' => $request->all(),
        ]);
    }

    public function ping(Request $request)
    {
        Log::info('WIFIDOG_PING : REQUEST ', $request->all());
        return response('Pong', 200)->header('Content-Type', 'text/plain');
    }
}
