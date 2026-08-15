<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];

        $data = [
            'user' => new UserResource($payload['user'] ?? null),
            'companies' => MembershipResource::collection($payload['companies'] ?? collect()),
            'current_company' => $payload['current_company']
                ? new CompanyResource($payload['current_company'])
                : null,
            'membership' => $payload['membership']
                ? new MembershipResource($payload['membership'])
                : null,
            'permissions' => $payload['permissions'] ?? [],
        ];

        if (array_key_exists('token', $payload)) {
            $data['token'] = $payload['token'];
        }

        return $data;
    }
}
