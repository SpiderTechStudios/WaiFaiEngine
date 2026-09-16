<?php

namespace App\Http\Controllers\Api\WiFiDog;

use App\Http\Controllers\Controller;
use App\Services\WiFiDogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WiFiDogController extends Controller
{
    public function __construct(private WiFiDogService $wiFiDogService) {}

    public function login(Request $request): RedirectResponse
    {
        return $this->wiFiDogService->login($request);
    }

    public function auth(Request $request): Response
    {
        return $this->wiFiDogService->auth($request);
    }

    public function portal(Request $request): RedirectResponse
    {
        return $this->wiFiDogService->portal($request);
    }

    public function ping(Request $request): Response
    {
        return $this->wiFiDogService->ping($request);
    }
}
