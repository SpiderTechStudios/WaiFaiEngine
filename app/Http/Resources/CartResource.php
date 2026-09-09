<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items ?? collect();
        $total = $items->sum(fn ($item) => (float) $item->unit_price * (int) $item->quantity);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'device_id' => $item->device_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => number_format((float) $item->unit_price * (int) $item->quantity, 2, '.', ''),
                'device' => $item->device ? [
                    'id' => $item->device->id,
                    'name' => $item->device->name,
                    'sku' => $item->device->sku,
                    'price' => $item->device->price,
                    'stock_quantity' => $item->device->stock_quantity,
                    'brand' => $item->device->brand ? [
                        'id' => $item->device->brand->id,
                        'name' => $item->device->brand->name,
                    ] : null,
                    'category' => $item->device->category ? [
                        'id' => $item->device->category->id,
                        'name' => $item->device->category->name,
                    ] : null,
                ] : null,
            ])->values(),
            'total_amount' => number_format($total, 2, '.', ''),
            'currency' => config('platform.currency', 'TZS'),
        ];
    }
}
