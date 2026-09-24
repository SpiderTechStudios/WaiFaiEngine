<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'duration' => $this->duration,
            'duration_unit' => $this->duration_unit,
            'max_claims' => $this->max_claims,
            'claims_count' => $this->claims_count,
            'remaining_claims' => $this->remainingClaims(),
            'is_active' => $this->is_active,
            'internet_plan_id' => $this->internet_plan_id,
            'package' => $this->whenLoaded('internetPlan', fn () => $this->internetPlan ? [
                'id' => $this->internetPlan->id,
                'name' => $this->internetPlan->name,
                'duration' => $this->internetPlan->duration,
                'duration_unit' => $this->internetPlan->duration_unit,
                'price' => $this->internetPlan->price,
            ] : null),
            'routers' => $this->whenLoaded('routers', fn () => $this->routers->map(fn ($router) => [
                'id' => $router->id,
                'name' => $router->name,
                'gateway_type' => $router->gateway_type,
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}
