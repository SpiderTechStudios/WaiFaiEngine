<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'paid_at' => $this->paid_at?->toDateString(),
            'paid_to' => $this->paid_to,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'status' => $this->status,
            'source' => $this->source,
            'category' => $this->category,
            'router' => $this->whenLoaded('router', fn () => $this->router ? [
                'id' => $this->router->id,
                'name' => $this->router->name,
            ] : null),
            'expense_type' => new ExpenseTypeResource($this->whenLoaded('expenseType')),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
