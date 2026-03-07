<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\WarehouseProduct;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        // Create sample warehouses
        $warehouses = [
            [
                'code' => 'WH001',
                'name' => 'Kho Hà Nội',
                'address' => '123 Đường Láng, Đống Đa, Hà Nội',
                'manager_name' => 'Nguyễn Văn Nam',
                'phone' => '0243856789',
                'email' => 'hanoi@warehouse.com',
                'capacity' => 200000000,
                'status' => 'active',
                'note' => 'Kho chính miền Bắc'
            ],
            [
                'code' => 'WH002',
                'name' => 'Kho TP.HCM',
                'address' => '456 Nguyễn Huệ, Quận 1, TP.HCM',
                'manager_name' => 'Trần Thị Mai',
                'phone' => '0283567891',
                'email' => 'hcm@warehouse.com',
                'capacity' => 300000000,
                'status' => 'active',
                'note' => 'Kho chính miền Nam'
            ],
            [
                'code' => 'WH003',
                'name' => 'Kho Đà Nẵng',
                'address' => '789 Hàn Thuyên, Hải Châu, Đà Nẵng',
                'manager_name' => 'Lê Văn Minh',
                'phone' => '02363789123',
                'email' => 'danang@warehouse.com',
                'capacity' => 150000000,
                'status' => 'maintenance',
                'note' => 'Kho miền Trung - đang bảo trì'
            ]
        ];

        foreach ($warehouses as $warehouseData) {
            Warehouse::create($warehouseData);
        }

        // Get all existing products and warehouses
        $products = Product::all();
        $activeWarehouses = Warehouse::where('status', 'active')->get();

        // Distribute products across warehouses
        foreach ($products as $product) {
            foreach ($activeWarehouses as $warehouse) {
                // Not all products are in all warehouses
                if (fake()->boolean(70)) { // 70% chance product is in this warehouse
                    $quantity = fake()->numberBetween(5, 50);
                    $minStock = fake()->numberBetween(3, 10);
                    $maxStock = $minStock + fake()->numberBetween(30, 100);
                    
                    WarehouseProduct::create([
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'cost' => $product->cost_price,
                        'min_stock' => $minStock,
                        'max_stock' => $maxStock,
                        'reserved_quantity' => fake()->numberBetween(0, min(3, $quantity)),
                        'last_import_date' => fake()->dateTimeBetween('-2 months'),
                        'last_export_date' => fake()->optional(60)->dateTimeBetween('-1 month'),
                    ]);
                }
            }
        }
    }
}