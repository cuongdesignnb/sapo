<?php

namespace App\Observers;

use App\Models\WarehouseProduct;
use App\Models\Warehouse;

class WarehouseProductObserver
{
    /**
     * Handle the WarehouseProduct "created" event.
     */
    public function created(WarehouseProduct $warehouseProduct): void
    {
        $this->updateWarehouseCurrentValue($warehouseProduct->warehouse_id);
    }

    /**
     * Handle the WarehouseProduct "updated" event.
     */
    public function updated(WarehouseProduct $warehouseProduct): void
    {
        $this->updateWarehouseCurrentValue($warehouseProduct->warehouse_id);
        
        // If warehouse changed, update both warehouses
        if ($warehouseProduct->isDirty('warehouse_id')) {
            $originalWarehouseId = $warehouseProduct->getOriginal('warehouse_id');
            if ($originalWarehouseId) {
                $this->updateWarehouseCurrentValue($originalWarehouseId);
            }
        }
    }

    /**
     * Handle the WarehouseProduct "deleted" event.
     */
    public function deleted(WarehouseProduct $warehouseProduct): void
    {
        $this->updateWarehouseCurrentValue($warehouseProduct->warehouse_id);
    }

    /**
     * Update warehouse current_value based on all products
     */
    private function updateWarehouseCurrentValue($warehouseId): void
    {
        if (!$warehouseId) return;

        $warehouse = Warehouse::find($warehouseId);
        if (!$warehouse) return;

        $currentValue = WarehouseProduct::where('warehouse_id', $warehouseId)
            ->selectRaw('SUM(quantity * cost) as total')
            ->value('total') ?? 0;

        $warehouse->update(['current_value' => $currentValue]);
    }
}