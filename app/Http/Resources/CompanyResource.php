<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subdomain' => $this->subdomain,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'setup_type' => $this->setup_type,
            'subscription_status' => $this->subscription_status,
            'installation_paid_at' => $this->installation_paid_at,
            'activated_at' => $this->activated_at,
            'subscription_period_ends_at' => $this->subscription_period_ends_at,
            'created_at' => $this->created_at,
        ];
    }
}
