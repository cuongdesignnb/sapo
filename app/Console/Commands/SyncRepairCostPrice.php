<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\Product;

class SyncRepairCostPrice extends Command
{
    protected $signature = 'repair:sync-cost';
    protected $description = 'Sync product cost_price with repair parts cost for existing tasks';

    public function handle()
    {
        $tasks = Task::where('type', 'repair')
            ->whereNotNull('product_id')
            ->where('parts_cost', '>', 0)
            ->with('parts')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No repair tasks with parts found.');
            return;
        }

        $updated = 0;
        foreach ($tasks as $task) {
            $product = Product::find($task->product_id);
            if (!$product) continue;

            // Calculate total parts cost for this specific task
            $partsCost = $task->parts->sum('total_cost');

            // Check if product cost already includes parts (original_cost + parts = expected)
            $expectedCost = (float) $task->original_cost + $partsCost;

            if ((float) $product->cost_price < $expectedCost) {
                $oldCost = $product->cost_price;
                $product->cost_price = $expectedCost;
                $product->save();
                $this->line("  [{$task->code}] {$product->name}: {$oldCost} → {$expectedCost} (+{$partsCost} linh kiện)");
                $updated++;
            }
        }

        $this->info("Done. Updated {$updated} product(s).");
    }
}
