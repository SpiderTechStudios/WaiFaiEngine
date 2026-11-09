<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        $methods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'slug' => $method->key,
                'name' => $method->name,
                'type' => $method->type,
                'logo_url' => $method->logo_url,
                'prefixes' => $method->prefixes,
            ])
            ->values();

        return $this->success($methods, 'Payment methods retrieved');
    }
}
