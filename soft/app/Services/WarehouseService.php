<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use App\Models\Product;
use App\Models\ProductSerial;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    /**
     * Get warehouse stock summary
     */
    public function getStockSummary($warehouseId = null)
    {
        $query = WarehouseProduct::with(['warehouse', 'product']);
        
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        
        return $query->get()->groupBy(function($item) {
            return $item->stock_status;
        });
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts($warehouseId = null)
    {
        $query = WarehouseProduct::with(['warehouse', 'product'])
            ->lowStock();
            
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        
        return $query->get();
    }

    /**
     * Get out of stock products
     */
    public function getOutOfStockProducts($warehouseId = null)
    {
        $query = WarehouseProduct::with(['warehouse', 'product'])
            ->outOfStock();
            
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        
        return $query->get();
    }

    /**
     * Transfer product between warehouses
     */
    public function transferProduct($fromWarehouseId, $toWarehouseId, $productId, $quantity, $note = null, $transferId = null, $serialIds = [])
{
    // Validate stock availability
    $fromWarehouseProduct = WarehouseProduct::where('warehouse_id', $fromWarehouseId)
        ->where('product_id', $productId)
        ->first();

    if (!$fromWarehouseProduct || $fromWarehouseProduct->quantity < $quantity) {
        throw new \Exception('Không đủ tồn kho để chuyển');
    }

    // Serial-tracked product validation
    $product = Product::find($productId);
    if ($product && $product->track_serial) {
        if (empty($serialIds)) {
            throw new \Exception("Sản phẩm '{$product->name}' yêu cầu chọn Serial/IMEI để chuyển kho.");
        }
        if (count($serialIds) !== $quantity) {
            throw new \Exception("Số serial đã chọn (" . count($serialIds) . ") phải bằng số lượng chuyển ({$quantity})");
        }
        // Verify all serials are available in the source warehouse
        $availableCount = ProductSerial::whereIn('id', $serialIds)
            ->where('product_id', $productId)
            ->where('warehouse_id', $fromWarehouseId)
            ->where('status', ProductSerial::STATUS_IN_STOCK)
            ->count();
        if ($availableCount !== count($serialIds)) {
            throw new \Exception("Một số serial không khả dụng trong kho nguồn.");
        }
    }

    // Create transfer item if transferId provided
    if ($transferId) {
        WarehouseTransferItem::create([
            'warehouse_transfer_id' => $transferId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'cost' => $fromWarehouseProduct->cost,
            'note' => $note
        ]);
    }

    // Decrease from source warehouse
    $fromWarehouseProduct->decrement('quantity', $quantity);

    // Increase to destination warehouse
    $toWarehouseProduct = WarehouseProduct::firstOrCreate(
        ['warehouse_id' => $toWarehouseId, 'product_id' => $productId],
        ['cost' => $fromWarehouseProduct->cost, 'quantity' => 0]
    );
    
    $toWarehouseProduct->increment('quantity', $quantity);

    // Transfer serials
    if ($product && $product->track_serial && !empty($serialIds)) {
        foreach ($serialIds as $serialId) {
            $serial = ProductSerial::find($serialId);
            if ($serial) {
                $serial->transferTo($toWarehouseId, auth()->id(), $note);
            }
        }
    }

    return [
        'product_id' => $productId,
        'quantity' => $quantity,
        'from_warehouse' => $fromWarehouseId,
        'to_warehouse' => $toWarehouseId
    ];
}

    /**
     * Adjust stock quantity
     */
    public function adjustStock($warehouseId, $productId, $newQuantity, $reason = null)
    {
        $warehouseProduct = WarehouseProduct::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();
            
        if (!$warehouseProduct) {
            throw new \Exception('Sản phẩm không tồn tại trong kho này');
        }
        
        $oldQuantity = $warehouseProduct->quantity;
        $warehouseProduct->update([
            'quantity' => $newQuantity,
            'last_import_date' => $newQuantity > $oldQuantity ? now() : $warehouseProduct->last_import_date,
            'last_export_date' => $newQuantity < $oldQuantity ? now() : $warehouseProduct->last_export_date,
        ]);
        
        return [
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'difference' => $newQuantity - $oldQuantity
        ];
    }

    /**
     * Get warehouse capacity analysis
     */
    public function getCapacityAnalysis($warehouseId)
    {
        $warehouse = Warehouse::with('warehouseProducts.product')->find($warehouseId);
        
        if (!$warehouse) {
            throw new \Exception('Kho không tồn tại');
        }
        
        return [
            'warehouse' => $warehouse,
            'capacity_usage_percent' => $warehouse->capacity_usage_percent,
            'total_products' => $warehouse->total_products,
            'total_product_types' => $warehouse->total_product_types,
            'current_value' => $warehouse->current_value,
            'capacity' => $warehouse->capacity,
            'available_capacity' => $warehouse->capacity - $warehouse->current_value,
        ];
    }

    /**
 * Bulk transfer products between warehouses
 */
public function bulkTransferProducts($fromWarehouseId, $toWarehouseId, $items, $note = null)
{
    DB::beginTransaction();
    
    try {
        // Create warehouse transfer record
        $transfer = WarehouseTransfer::create([
            'code' => 'BT' . now()->format('YmdHis'),
            'warehouse_from' => $fromWarehouseId,
            'warehouse_to' => $toWarehouseId,
            'created_by' => auth()->id(),
            'status' => 'pending',
            'note' => $note,
            'transfered_at' => now()
        ]);

        $results = [];
        
        foreach ($items as $item) {
            $result = $this->transferProduct(
                $fromWarehouseId,
                $toWarehouseId,
                $item['product_id'],
                $item['quantity'],
                $item['note'] ?? null,
                $transfer->id
            );
            
            $results[] = $result;
        }

        // Update transfer status to completed
        $transfer->update(['status' => 'completed']);

        DB::commit();
        
        return [
            'transfer' => $transfer,
            'items' => $results,
            'total_items' => count($results)
        ];
        
    } catch (\Exception $e) {
        DB::rollBack();
        throw new \Exception('Lỗi chuyển kho hàng loạt: ' . $e->getMessage());
    }
}
}