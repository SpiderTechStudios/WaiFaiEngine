<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\UpdateSettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settingsService) {}

    public function show(): JsonResponse
    {
        return $this->success($this->settingsService->show($this->currentCompany()), 'Settings retrieved');
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->settingsService->update($this->currentCompany(), $request->validated(), $request->user());

        return $this->success($settings, 'Settings updated');
    }
}
