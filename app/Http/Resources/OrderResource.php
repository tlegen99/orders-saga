<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Order $resource
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'status' => $this->resource->status->value,
            'amount' => $this->resource->amount,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
