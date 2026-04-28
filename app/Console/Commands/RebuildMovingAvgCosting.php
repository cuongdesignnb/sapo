<?php

namespace App\Console\Commands;

use App\Models\InvoiceItem;
use App\Models\InvoiceItemSerial;
use App\Models\Product;
use App\Models\SerialImei;
use App\Models\Task;
use App\Models\TaskPart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild giá vốn bình quân gia quyền (weighted moving average) cho toàn bộ
 * lịch sử của 1 hoặc nhiều sản phẩm.
 *
 * NGUỒN DỮ LIỆU DUY NHẤT (source of truth):
 *  1. purchase_items       — Nhập mua hàng        (+S, +T)
 *  2. invoice_items        — Bán hàng              (-S, -T)
 *  3. return_items         — Khách trả hàng        (+S, +T)
 *  4. purchase_return_items — Trả hàng NCC         (-S, -T)
 *  5. stock_take_items     — Kiểm kho              (±S, ±T)
 *  6. damage_items         — Xuất hủy              (-S, -T)
 *  7. task_parts           — Linh kiện sửa chữa    (±S, ±T cho LINH KIỆN)
 *  8. task_parts via task  — Sửa máy               (S giữ, ±T cho MÁY)
 *
 * KHÔNG ĐỌC stock_movements (trừ debug) — tránh đếm đôi.
 * KHÔNG dùng initial_balance — tất cả SP đều có phiếu nhập đầy đủ.
 *
 * Cập nhật:
 *  - products.stock_quantity, cost_price (BQ), inventory_total_cost
 *  - invoice_items.cost_price (COGS = BQ tại lúc bán)
 *  - serial_imeis.sold_cost_price
 *
 * Chạy:
 *   php artisan costing:rebuild-moving-avg --product=ID --dry-run
 *   php artisan costing:rebuild-moving-avg --all
 */
class RebuildMovingAvgCosting extends Command
{
    protected $signature = 'costing:rebuild-moving-avg
        {--product= : Product ID hoặc SKU}
        {--all : Rebuild toàn bộ sản phẩm}
        {--dry-run : Chỉ tính toán, không ghi DB}';

    protected $description = 'Rebuild giá vốn bình quân gia quyền từ source tables';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');
        $productOpt = $this->option('product');

        if (!$all && !$productOpt) {
            $this->error('Phải cung cấp --product=ID|SKU hoặc --all');
            return self::FAILURE;
        }

        $query = Product::query();
        if (!$all) {
            $query->where(function ($q) use ($productOpt) {
                $q->where('id', $productOpt)->orWhere('sku', $productOpt);
            });
        }
        $products = $query->orderBy('id')->get();
        if ($products->isEmpty()) {
            $this->error('Không tìm thấy sản phẩm.');
            return self::FAILURE;
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . 'Rebuild ' . $products->count() . ' sản phẩm');

        $totals = ['products' => 0, 'invoice_items_updated' => 0, 'serials_updated' => 0];

        foreach ($products as $product) {
            $stats = $this->rebuildOne($product, $dry);
            $totals['products']++;
            $totals['invoice_items_updated'] += $stats['invoice_items_updated'];
            $totals['serials_updated'] += $stats['serials_updated'];
        }

        $this->newLine();
        $this->info('───────── TỔNG KẾT ─────────');
        $this->line('Sản phẩm: ' . $totals['products']);
        $this->line('Invoice items cập nhật: ' . $totals['invoice_items_updated']);
        $this->line('Serials cập nhật: ' . $totals['serials_updated']);
        if ($dry) {
            $this->warn('DRY-RUN: KHÔNG có gì được ghi vào DB.');
        }
        return self::SUCCESS;
    }

    private function rebuildOne(Product $product, bool $dry): array
    {
        $stats = ['invoice_items_updated' => 0, 'serials_updated' => 0];

        $events = $this->buildTimeline($product);
        if (empty($events)) {
            $this->line(sprintf('  • #%d %s: không có lịch sử, bỏ qua.', $product->id, $product->sku));
            return $stats;
        }

        $cb = function () use ($product, $events, $dry, &$stats) {
            $qty = 0;    // S — số lượng tồn
            $total = 0.0; // T — tổng giá trị tồn kho

            // Lưu COGS tại thời điểm bán để hoàn lại đúng khi khách trả
            $invoiceCogsMap = [];
            // Theo dõi serial đã bán → skip repair trên serial đã bán
            $soldSerials = [];
            $skippedRepairs = 0;
            $skippedRepairCost = 0.0;

            foreach ($events as $e) {
                switch ($e['kind']) {
                    // ═══ 1. NHẬP MUA ═══
                    case 'purchase':
                        $qty += $e['qty'];
                        $total += $e['qty'] * $e['unit_cost'];
                        break;

                    // ═══ 2. BÁN HÀNG ═══
                    case 'sale':
                        $bq = $qty > 0 ? ($total / $qty) : 0;
                        $cogsTotal = $bq * $e['qty'];
                        $total = max(0, $total - $cogsTotal);
                        $qty = max(0, $qty - $e['qty']);

                        // Ghi COGS vào invoice_items
                        if ($e['invoice_item_id']) {
                            $this->writeInvoiceItemCost($e['invoice_item_id'], $bq, $dry, $stats);
                        }
                        // Ghi sold_cost_price vào serial
                        if (!empty($e['serial_imei_ids'])) {
                            foreach ($e['serial_imei_ids'] as $sid) {
                                $soldSerials[$sid] = true;
                                $this->writeSerialSoldCost($sid, $bq, $dry, $stats);
                                if ($e['invoice_item_id']) {
                                    $this->writeInvoiceItemSerialCost($e['invoice_item_id'], $sid, $bq, $dry, $stats);
                                }
                            }
                        }
                        $invoiceCogsMap[$e['invoice_id']] = $bq;
                        break;

                    // ═══ 3. KHÁCH TRẢ HÀNG ═══
                    case 'sale_return':
                        $cogs = $invoiceCogsMap[$e['invoice_id']] ?? ($e['unit_cost'] ?: 0);
                        $total += $e['qty'] * $cogs;
                        $qty += $e['qty'];
                        if (!empty($e['serial_imei_ids'])) {
                            foreach ($e['serial_imei_ids'] as $sid) {
                                unset($soldSerials[$sid]);
                            }
                        }
                        break;

                    // ═══ 4. TRẢ HÀNG NCC ═══
                    case 'purchase_return':
                        $total = max(0, $total - $e['qty'] * $e['unit_cost']);
                        $qty = max(0, $qty - $e['qty']);
                        break;

                    // ═══ 5. KIỂM KHO ═══
                    case 'stock_take':
                        $bq = $qty > 0 ? ($total / $qty) : 0;
                        $diff = $e['diff']; // actual - system, có thể âm hoặc dương
                        $qty += $diff;
                        $total += $diff * $bq;
                        if ($qty < 0) $qty = 0;
                        if ($total < 0) $total = 0;
                        break;

                    // ═══ 6. XUẤT HỦY ═══
                    case 'damage':
                        $bq = $qty > 0 ? ($total / $qty) : 0;
                        $total = max(0, $total - $bq * $e['qty']);
                        $qty = max(0, $qty - $e['qty']);
                        break;

                    // ═══ 7. LINH KIỆN XUẤT SỬA CHỮA ═══
                    // (SP này là LINH KIỆN, xuất khỏi kho → S giảm, T giảm)
                    case 'part_export':
                        $qty = max(0, $qty - $e['qty']);
                        $total = max(0, $total - $e['cost']);
                        break;

                    // ═══ 8. LINH KIỆN NHẬP BÓC MÁY ═══
                    // (SP này là LINH KIỆN, nhập vào kho từ bóc máy → S tăng, T tăng)
                    case 'part_import':
                        $qty += $e['qty'];
                        $total += $e['cost'];
                        break;

                    // ═══ 9. SỬA MÁY (LẮP LINH KIỆN VÀO MÁY) ═══
                    // (SP này là MÁY, S giữ nguyên, T tăng)
                    case 'repair_on_machine_in':
                        if (!empty($e['serial_imei_id']) && isset($soldSerials[$e['serial_imei_id']])) {
                            $skippedRepairs++;
                            $skippedRepairCost += $e['cost'];
                            break;
                        }
                        $total += $e['cost'];
                        // S KHÔNG ĐỔI
                        break;

                    // ═══ 10. SỬA MÁY (THÁO LINH KIỆN KHỎI MÁY) ═══
                    // (SP này là MÁY, S giữ nguyên, T giảm)
                    case 'repair_on_machine_out':
                        if (!empty($e['serial_imei_id']) && isset($soldSerials[$e['serial_imei_id']])) {
                            $skippedRepairs++;
                            $skippedRepairCost += $e['cost'];
                            break;
                        }
                        $total = max(0, $total - $e['cost']);
                        // S KHÔNG ĐỔI
                        break;
                }
            }

            // Kết quả cuối
            $bq = $qty > 0 ? round($total / $qty, 2) : 0;
            $this->line(sprintf(
                '  • #%d %s: qty=%d total=%s BQ=%s (cũ: qty=%d BQ=%s total=%s)',
                $product->id, $product->sku,
                $qty, number_format($total, 2), number_format($bq, 2),
                $product->stock_quantity,
                number_format((float) $product->cost_price, 2),
                number_format((float) $product->inventory_total_cost, 2)
            ));
            if ($skippedRepairs > 0) {
                $this->warn(sprintf(
                    '    ⓘ Bỏ qua %d phụ tùng trên serial đã bán (tổng %s) — không cộng vào giá vốn.',
                    $skippedRepairs, number_format($skippedRepairCost, 0)
                ));
            }

            if (!$dry) {
                $product->stock_quantity = $qty;
                $product->cost_price = $bq;
                $product->inventory_total_cost = round($total, 2);
                $product->saveQuietly();
            }
        };

        if ($dry) {
            $cb();
        } else {
            DB::transaction($cb);
        }

        return $stats;
    }

    /**
     * Tạo timeline sự kiện cho 1 sản phẩm, sắp xếp theo thời gian.
     *
     * Source of truth duy nhất: các bảng giao dịch gốc.
     * KHÔNG đọc stock_movements để tránh đếm đôi.
     */
    private function buildTimeline(Product $product): array
    {
        $events = [];

        // ═══════════════════════════════════════════════════════
        // 1. NHẬP MUA — purchase_items (phiếu nhập hoàn thành)
        // ═══════════════════════════════════════════════════════
        $purchaseItems = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.product_id', $product->id)
            ->where('purchases.status', 'completed')
            ->orderBy('purchases.created_at')
            ->get(['purchase_items.*', 'purchases.created_at as ts']);

        foreach ($purchaseItems as $pi) {
            $unitCost = (float) ($pi->unit_cost_allocated ?? $pi->price ?? 0);
            if ($pi->quantity <= 0) continue;
            $events[] = [
                'kind' => 'purchase',
                'ts' => $pi->ts,
                'sort_key' => strtotime($pi->ts) * 10 + 0, // ×10 để tạo sub-order
                'qty' => (int) $pi->quantity,
                'unit_cost' => $unitCost,
            ];
        }

        // ═══════════════════════════════════════════════════════
        // 2. BÁN HÀNG — invoice_items (hóa đơn chưa hủy)
        // ═══════════════════════════════════════════════════════
        $invItems = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.product_id', $product->id)
            ->where(function ($q) {
                $q->whereNull('invoices.status')
                  ->orWhere('invoices.status', '!=', 'cancelled');
            })
            ->orderBy('invoices.created_at')
            ->get(['invoice_items.*', 'invoices.created_at as ts']);

        foreach ($invItems as $ii) {
            if ($ii->quantity <= 0) continue;

            // Tìm serial_imei_ids nếu hàng serial
            $serialIds = [];
            if ($product->has_serial) {
                $serialIds = DB::table('invoice_item_serials')
                    ->where('invoice_item_id', $ii->id)
                    ->pluck('serial_imei_id')
                    ->toArray();
                // Fallback: serial gắn trực tiếp qua invoice_id
                if (empty($serialIds)) {
                    $serialIds = SerialImei::where('product_id', $product->id)
                        ->where('invoice_id', $ii->invoice_id)
                        ->pluck('id')
                        ->toArray();
                }
            }

            $events[] = [
                'kind' => 'sale',
                'ts' => $ii->ts,
                'sort_key' => strtotime($ii->ts) * 10 + 4,
                'qty' => (int) $ii->quantity,
                'invoice_id' => (int) $ii->invoice_id,
                'invoice_item_id' => (int) $ii->id,
                'serial_imei_ids' => $serialIds,
            ];
        }

        // ═══════════════════════════════════════════════════════
        // 3. KHÁCH TRẢ HÀNG — return_items (chưa hủy)
        // ═══════════════════════════════════════════════════════
        $returnItems = DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('return_items.product_id', $product->id)
            ->where(function ($q) {
                $q->where('returns.status', '!=', 'Đã hủy')
                  ->orWhereNull('returns.status');
            })
            ->orderBy('returns.created_at')
            ->get(['return_items.*', 'returns.created_at as ts', 'returns.invoice_id']);

        foreach ($returnItems as $ri) {
            if ($ri->quantity <= 0) continue;
            $events[] = [
                'kind' => 'sale_return',
                'ts' => $ri->ts,
                'sort_key' => strtotime($ri->ts) * 10 + 6,
                'qty' => (int) $ri->quantity,
                'unit_cost' => (float) ($ri->cost_price ?? 0),
                'invoice_id' => $ri->invoice_id,
                'serial_imei_ids' => [],
            ];
        }

        // ═══════════════════════════════════════════════════════
        // 4. TRẢ HÀNG NCC — purchase_return_items (hoàn thành)
        // ═══════════════════════════════════════════════════════
        $prItems = DB::table('purchase_return_items')
            ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->where('purchase_return_items.product_id', $product->id)
            ->where('purchase_returns.status', 'completed')
            ->orderBy('purchase_returns.created_at')
            ->get(['purchase_return_items.*', 'purchase_returns.created_at as ts']);

        foreach ($prItems as $pri) {
            if ($pri->quantity <= 0) continue;
            $events[] = [
                'kind' => 'purchase_return',
                'ts' => $pri->ts,
                'sort_key' => strtotime($pri->ts) * 10 + 7,
                'qty' => (int) $pri->quantity,
                'unit_cost' => (float) ($pri->cost_price ?? $pri->price ?? 0),
            ];
        }

        // ═══════════════════════════════════════════════════════
        // 5. KIỂM KHO — stock_take_items (phiếu đã cân bằng)
        // ═══════════════════════════════════════════════════════
        if (DB::getSchemaBuilder()->hasTable('stock_take_items')) {
            $stItems = DB::table('stock_take_items')
                ->join('stock_takes', 'stock_takes.id', '=', 'stock_take_items.stock_take_id')
                ->where('stock_take_items.product_id', $product->id)
                ->where('stock_takes.status', 'balanced')
                ->orderBy('stock_takes.balanced_date')
                ->get(['stock_take_items.*', 'stock_takes.balanced_date as ts']);

            foreach ($stItems as $sti) {
                $diff = (int) $sti->actual_stock - (int) $sti->system_stock;
                if ($diff === 0) continue;
                $events[] = [
                    'kind' => 'stock_take',
                    'ts' => $sti->ts,
                    'sort_key' => strtotime($sti->ts) * 10 + 5,
                    'diff' => $diff,
                ];
            }
        }

        // ═══════════════════════════════════════════════════════
        // 6. XUẤT HỦY — damage_items (phiếu hoàn thành)
        // ═══════════════════════════════════════════════════════
        if (DB::getSchemaBuilder()->hasTable('damage_items')) {
            $dmgItems = DB::table('damage_items')
                ->join('damages', 'damages.id', '=', 'damage_items.damage_id')
                ->where('damage_items.product_id', $product->id)
                ->where('damages.status', 'completed')
                ->orderBy('damages.created_at')
                ->get(['damage_items.*', 'damages.created_at as ts']);

            foreach ($dmgItems as $di) {
                if ($di->qty <= 0) continue;
                $events[] = [
                    'kind' => 'damage',
                    'ts' => $di->ts,
                    'sort_key' => strtotime($di->ts) * 10 + 5,
                    'qty' => (int) $di->qty,
                ];
            }
        }

        // ═══════════════════════════════════════════════════════
        // 7 & 8. TASK_PARTS — 2 ngữ cảnh khác nhau:
        //   - LINH KIỆN (task_parts.product_id = SP này): S thay đổi
        //   - MÁY (task.product_id hoặc task.serial_imei_id → SP này): chỉ T thay đổi
        // ═══════════════════════════════════════════════════════
        if (DB::getSchemaBuilder()->hasTable('task_parts')) {
            // 7a. SP này LÀ LINH KIỆN bị xuất/nhập
            $componentParts = TaskPart::where('product_id', $product->id)
                ->orderBy('created_at')->orderBy('id')
                ->get();

            foreach ($componentParts as $p) {
                $isImport = ($p->direction ?? 'export') === 'import';
                $events[] = [
                    'kind' => $isImport ? 'part_import' : 'part_export',
                    'ts' => $p->created_at,
                    'sort_key' => strtotime($p->created_at) * 10 + 2,
                    'qty' => (int) ($p->quantity ?? 1),
                    'cost' => (float) $p->total_cost,
                ];
            }

            // 7b. SP này LÀ MÁY được sửa (task gắn với product hoặc serial của product)
            $taskIds = Task::where(function ($q) use ($product) {
                    $q->where('product_id', $product->id);
                    $q->orWhereHas('serialImei', fn($s) => $s->where('product_id', $product->id));
                })
                ->pluck('id')
                ->toArray();

            if (!empty($taskIds)) {
                $tasksById = Task::whereIn('id', $taskIds)->get(['id', 'serial_imei_id'])->keyBy('id');

                // Lấy task_parts MÀ product_id ≠ SP này (linh kiện khác được lắp VÀO máy này)
                $machineParts = TaskPart::whereIn('task_id', $taskIds)
                    ->where('product_id', '!=', $product->id)
                    ->orderBy('created_at')->orderBy('id')
                    ->get();

                foreach ($machineParts as $p) {
                    $isImport = ($p->direction ?? 'export') === 'import';
                    $task = $tasksById->get($p->task_id);

                    // export linh kiện = lắp vào máy → T máy TĂNG
                    // import linh kiện (bóc máy) = tháo khỏi máy → T máy GIẢM
                    $events[] = [
                        'kind' => $isImport ? 'repair_on_machine_out' : 'repair_on_machine_in',
                        'ts' => $p->created_at,
                        'sort_key' => strtotime($p->created_at) * 10 + 2,
                        'cost' => (float) $p->total_cost,
                        'serial_imei_id' => $task?->serial_imei_id,
                    ];
                }
            }
        }

        // ═══════════════════════════════════════════════════════
        // SẮP XẾP: theo thời gian, sau đó theo loại
        // Thứ tự: nhập → sửa chữa → bán → trả hàng
        // ═══════════════════════════════════════════════════════
        usort($events, fn($a, $b) => $a['sort_key'] <=> $b['sort_key']);

        return $events;
    }

    // ═══════════════════════════════════════════════════════
    // HELPER METHODS — Ghi kết quả vào DB
    // ═══════════════════════════════════════════════════════

    private function writeInvoiceItemCost(?int $invoiceItemId, float $bq, bool $dry, array &$stats): void
    {
        if (!$invoiceItemId) return;
        if ($dry) { $stats['invoice_items_updated']++; return; }
        InvoiceItem::where('id', $invoiceItemId)->update(['cost_price' => round($bq, 0)]);
        $stats['invoice_items_updated']++;
    }

    private function writeInvoiceItemSerialCost(?int $invoiceItemId, int $serialId, float $bq, bool $dry, array &$stats): void
    {
        if (!$invoiceItemId) return;
        if ($dry) return;
        InvoiceItemSerial::where('invoice_item_id', $invoiceItemId)
            ->where('serial_imei_id', $serialId)
            ->update(['cost_price' => round($bq, 0)]);
    }

    private function writeSerialSoldCost(int $serialId, float $bq, bool $dry, array &$stats): void
    {
        if ($dry) { $stats['serials_updated']++; return; }
        SerialImei::where('id', $serialId)
            ->whereNotNull('sold_at')
            ->update(['sold_cost_price' => round($bq, 0)]);
        $stats['serials_updated']++;
    }
}
