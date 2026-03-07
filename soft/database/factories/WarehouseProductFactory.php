<?php

namespace Database\Factories;

use App\Models\WarehouseProduct;
use App\Models\Warehouse;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseProductFactory extends Factory
{
    protected $model = WarehouseProduct::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(0, 100);
        $minStock = fake()->numberBetween(5, 20);
        $maxStock = $minStock + fake()->numberBetween(50, 200);
        
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'cost' => fake()->randomFloat(2, 100000, 10000000), // 100K - 10M
            'min_stock' => $minStock,
            'max_stock' => $maxStock,
            'reserved_quantity' => fake()->numberBetween(0, min(5, $quantity)),
            'last_import_date' => fake()->optional()->dateTimeBetween('-3 months'),
            'last_export_date' => fake()->optional()->dateTimeBetween('-1 month'),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(1, 5),
            'min_stock' => fake()->numberBetween(10, 20),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);
    }

    public function overStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(200, 500),
            'max_stock' => fake()->numberBetween(50, 100),
        ]);
    }

    public function inStock(): static
    {
        return $this->state(function (array $attributes) {
            $minStock = fake()->numberBetween(10, 20);
            $maxStock = $minStock + fake()->numberBetween(50, 100);
            $quantity = fake()->numberBetween($minStock + 5, $maxStock - 5);
            
            return [
                'quantity' => $quantity,
                'min_stock' => $minStock,
                'max_stock' => $maxStock,
            ];
        });
    }
}