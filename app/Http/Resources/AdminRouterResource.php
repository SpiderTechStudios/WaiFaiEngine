<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRouterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            (new RouterResource($this->resource))->resolve($request),
            [
                'company' => $this->whenLoaded(
                    'company',
                    fn () => (new CompanyResource($this->company))->resolve($request),
                ),
            ],
        );
    }
}
