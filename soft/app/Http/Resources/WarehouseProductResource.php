<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseProductResource extends JsonResource
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
            'warehouse_id' => $this->warehouse_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'cost' => $this->cost,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'reserved_quantity' => $this->reserved_quantity,
            'available_stock' => $this->available_stock,
            'stock_status' => $this->stock_status,
            'stock_status_label' => $this->stock_status_label,
            'stock_status_color' => $this->stock_status_color,
            'total_value' => $this->total_value,
            'reserved_percent' => $this->reserved_percent,
            'last_import_date' => $this->last_import_date?->format('d/m/Y H:i'),
            'last_export_date' => $this->last_export_date?->format('d/m/Y H:i'),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'product' => [
                'id' => $this->product?->id,
                'sku' => $this->product?->sku,
                'name' => $this->product?->name,
                'barcode' => $this->product?->barcode,
                'category_name' => $this->product?->category_name,
                'brand_name' => $this->product?->brand_name,
                'formatted_cost_price' => $this->product?->formatted_cost_price,
                'formatted_retail_price' => $this->product?->formatted_retail_price,
            ],
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i'),
        ];
    }
}