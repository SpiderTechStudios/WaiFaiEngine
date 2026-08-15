<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'joined_at' => $this->joined_at,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'user' => new UserResource($this->whenLoaded('user')),
            'role' => new RoleResource($this->whenLoaded('role')),
        ];
    }
}
