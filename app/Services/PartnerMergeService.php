<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\PartnerMerge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PartnerMergeService
{
    public function preview(Customer $source, Customer $target): array
    {
        $sourceDebt = (float) $source->debt_amount;
        $sourceSupplierDebt = (float) $source->supplier_debt_amount;
        $targetDebt = (float) $target->debt_amount;
        $targetSupplierDebt = (float) $target->supplier_debt_amount;
        $debtAfter = $sourceDebt + $targetDebt;
        $supplierDebtAfter = $sourceSupplierDebt + $targetSupplierDebt;

        return [
            'before' => [
                'source' => $this->partnerSnapshot($source),
                'target' => $this->partnerSnapshot($target),
            ],
            'after' => [
                'debt_amount' => $debtAfter,
                'supplier_debt_amount' => $supplierDebtAfter,
                'customer_net_position' => $debtAfter - $supplierDebtAfter,
                'supplier_net_position' => $supplierDebtAfter - $debtAfter,
            ],
            'marker' => [
                'code' => $this->markerCode($source->id, $target->id),
                'amount' => 0.0,
                'affects_debt_balance' => false,
                'is_reference_only' => true,
            ],
        ];
    }

    public function merge(Customer $source, Customer $target): array
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['merge_with_id' => 'Không thể gộp đối tác với chính mình.']);
        }

        return DB::transaction(function () use ($source, $target) {
            $partners = Customer::query()
                ->whereIn('id', [$source->id, $target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lockedSource = $partners->get($source->id);
            $lockedTarget = $partners->get($target->id);

            if (!$lockedSource || !$lockedTarget) {
                throw ValidationException::withMessages(['merge_with_id' => 'Không tìm thấy đối tác để gộp.']);
            }
            if ($lockedSource->merged_into_id) {
                throw ValidationException::withMessages(['merge_with_id' => 'Đối tác đã được gộp.']);
            }
            if ($lockedTarget->merged_into_id || $lockedTarget->status === 'inactive') {
                throw ValidationException::withMessages(['merge_with_id' => 'Đối tác đích không còn hoạt động.']);
            }

            $markerCode = $this->markerCode($lockedSource->id, $lockedTarget->id);
            if (PartnerMerge::where('ref_code', $markerCode)->exists()) {
                throw ValidationException::withMessages(['merge_with_id' => 'Đối tác đã được gộp.']);
            }

            $preview = $this->preview($lockedSource, $lockedTarget);
            PartnerMerge::create([
                'ref_code' => $markerCode,
                'source_partner_id' => $lockedSource->id,
                'target_partner_id' => $lockedTarget->id,
                'source_debt_amount' => $lockedSource->debt_amount,
                'source_supplier_debt_amount' => $lockedSource->supplier_debt_amount,
                'target_debt_amount_before' => $lockedTarget->debt_amount,
                'target_supplier_debt_amount_before' => $lockedTarget->supplier_debt_amount,
                'merged_by' => auth()->id(),
                'merged_at' => now(),
            ]);

            $this->transferRelations($lockedSource->id, $lockedTarget->id, $lockedTarget->name);

            $lockedTarget->debt_amount = $preview['after']['debt_amount'];
            $lockedTarget->supplier_debt_amount = $preview['after']['supplier_debt_amount'];
            $lockedTarget->total_spent = (float) $lockedTarget->total_spent + (float) $lockedSource->total_spent;
            $lockedTarget->total_returns = (float) $lockedTarget->total_returns + (float) $lockedSource->total_returns;
            $lockedTarget->total_bought = (float) $lockedTarget->total_bought + (float) $lockedSource->total_bought;
            $lockedTarget->is_customer = (bool) ($lockedTarget->is_customer || $lockedSource->is_customer);
            $lockedTarget->is_supplier = (bool) ($lockedTarget->is_supplier || $lockedSource->is_supplier);
            $lockedTarget->save();

            CustomerDebt::firstOrCreate(
                ['customer_id' => $lockedTarget->id, 'ref_code' => $markerCode],
                [
                    'amount' => 0,
                    'debt_total' => (float) $lockedTarget->debt_amount,
                    'type' => 'merge_marker',
                    'note' => "Gộp hồ sơ {$lockedSource->code} vào {$lockedTarget->code}",
                    'created_by' => auth()->id(),
                    'recorded_at' => now(),
                ]
            );

            $lockedSource->forceFill([
                'debt_amount' => 0,
                'supplier_debt_amount' => 0,
                'total_spent' => 0,
                'total_returns' => 0,
                'total_bought' => 0,
                'status' => 'inactive',
                'merged_into_id' => $lockedTarget->id,
                'merged_at' => now(),
            ])->save();

            ActivityLog::log(
                'partner_merge',
                "Gộp đối tác {$lockedSource->code} vào {$lockedTarget->code}",
                $lockedTarget,
                ['source_partner_id' => $lockedSource->id, 'marker_code' => $markerCode]
            );

            return $preview;
        });
    }

    private function transferRelations(int $sourceId, int $targetId, string $targetName): void
    {
        $relations = [
            ['invoices', 'customer_id'],
            ['orders', 'customer_id'],
            ['returns', 'customer_id'],
            ['purchases', 'supplier_id'],
            ['purchase_orders', 'supplier_id'],
            ['purchase_returns', 'supplier_id'],
            ['customer_debts', 'customer_id'],
            ['supplier_debt_transactions', 'supplier_id'],
            ['debt_offsets', 'customer_id'],
            ['customer_payment_allocations', 'customer_id'],
            ['customer_payment_discounts', 'customer_id'],
            ['customer_payment_discount_allocations', 'customer_id'],
            ['customer_delivery_addresses', 'customer_id'],
            ['promotion_usages', 'customer_id'],
            ['tasks', 'customer_id'],
            ['waybills', 'customer_id'],
        ];

        foreach ($relations as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->where($column, $sourceId)->update([$column => $targetId]);
            }
        }

        CashFlow::withTrashed()
            ->where('target_id', $sourceId)
            ->whereIn('target_type', ['Khách hàng', 'Nhà cung cấp'])
            ->update(['target_id' => $targetId, 'target_name' => $targetName]);

        foreach ($relations as [$table, $column]) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, $column)
                && DB::table($table)->where($column, $sourceId)->exists()
            ) {
                throw ValidationException::withMessages([
                    'merge_with_id' => "Không thể chuyển hết dữ liệu liên quan trong bảng {$table}.",
                ]);
            }
        }
    }

    private function partnerSnapshot(Customer $partner): array
    {
        return [
            'id' => $partner->id,
            'code' => $partner->code,
            'name' => $partner->name,
            'debt_amount' => (float) $partner->debt_amount,
            'supplier_debt_amount' => (float) $partner->supplier_debt_amount,
            'total_spent' => (float) $partner->total_spent,
            'total_bought' => (float) $partner->total_bought,
            'document_counts' => [
                'invoices' => $partner->invoices()->count(),
                'orders' => $partner->orders()->count(),
                'returns' => $partner->returns()->count(),
                'purchases' => $partner->purchases()->count(),
            ],
        ];
    }

    private function markerCode(int $sourceId, int $targetId): string
    {
        return "MERGE-PARTNER-{$sourceId}-TO-{$targetId}";
    }
}
