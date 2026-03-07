<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'manager_name' => $this->manager_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'capacity' => $this->capacity,
            'current_value' => $this->current_value,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'note' => $this->note,
            'capacity_usage_percent' => $this->capacity_usage_percent,
            'total_products' => $this->total_products,
            'total_product_types' => $this->total_product_types,
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i'),
        ];
    }
}