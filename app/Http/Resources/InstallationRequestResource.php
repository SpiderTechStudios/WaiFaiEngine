<?php

namespace App\Http\Resources;

use App\Services\InstallationRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallationRequestResource extends JsonResource
{
    public function __construct($resource, private bool $includeInternal = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = app(InstallationRequestService::class)->progressSteps($this->resource);

        $updates = $this->whenLoaded('updates', function () {
            $items = $this->updates;
            if (! $this->includeInternal) {
                $items = $items->where('visibility', 'customer')->values();
            }

            return $items->map(fn ($update) => [
                'id' => $update->id,
                'visibility' => $update->visibility,
                'body' => $update->body,
                'actor' => $update->actor ? [
                    'id' => $update->actor->id,
                    'name' => trim($update->actor->first_name.' '.$update->actor->last_name),
                ] : null,
                'created_at' => $update->created_at,
            ])->values();
        });

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'service_type' => $this->service_type,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'payment_status' => $this->payment_status,
            'fulfillment_status' => $this->fulfillment_status,
            'paid_at' => $this->paid_at,
            'scheduled_at' => $this->scheduled_at,
            'customer_notes' => $this->customer_notes,
            'progress' => $progress,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'position' => $item->position,
                'label' => $item->label,
                'status' => $item->status,
                'network_device_id' => $item->network_device_id,
            ])->values()),
            'status_history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($row) => [
                'field' => $row->field,
                'from_status' => $row->from_status,
                'to_status' => $row->to_status,
                'note' => $row->note,
                'actor' => $row->actor ? [
                    'id' => $row->actor->id,
                    'name' => trim($row->actor->first_name.' '.$row->actor->last_name),
                ] : null,
                'created_at' => $row->created_at,
            ])->values()),
            'updates' => $updates,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
