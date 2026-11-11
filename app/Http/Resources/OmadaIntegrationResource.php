<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OmadaIntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'deployment_type' => $this->deployment_type,
            'controller_url' => $this->controller_url,
            'controller_host' => $this->controller_host,
            'controller_port' => $this->controller_port,
            'controller_id' => $this->controller_id,
            'controller_version' => $this->controller_version,
            'connection_method' => $this->connection_method,
            'verify_tls' => (bool) $this->verify_tls,
            'site_id' => $this->site_id,
            'site_name' => $this->site_name,
            'site_timezone' => $this->site_timezone,
            'connection_status' => $this->connection_status,
            'status' => $this->status,
            'last_connected_at' => $this->last_connected_at,
            'last_synced_at' => $this->last_synced_at,
            'credentials_set' => [
                'client_id' => filled($this->credential('client_id')),
                'client_secret' => filled($this->credential('client_secret')),
                'operator_username' => filled($this->credential('operator_username')),
                'operator_password' => filled($this->credential('operator_password')),
            ],
            'portal_url' => $this->portal_key ? url('/api/omada/portal/'.$this->portal_key) : null,
            'router_id' => $this->network_device_id,
            'sites_count' => $this->whenCounted('sites'),
            'access_points_count' => $this->whenCounted('accessPoints'),
            'ssids_count' => $this->whenCounted('ssids'),
            'active_clients_count' => $this->whenCounted('clientSessions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
