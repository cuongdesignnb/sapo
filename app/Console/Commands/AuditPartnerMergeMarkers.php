<?php

namespace App\Console\Commands;

use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\Invoice;
use App\Models\Purchase;
use Illuminate\Console\Command;

class AuditPartnerMergeMarkers extends Command
{
    protected $signature = 'partners:audit-merge-markers
        {--partner= : Chỉ kiểm tra một partner id}
        {--json : Xuất JSON}';

    protected $description = 'Report legacy MERGE debt rows without changing data';

    public function handle(): int
    {
        $query = CustomerDebt::query()
            ->where('ref_code', 'like', 'MERGE%')
            ->when($this->option('partner'), fn ($q, $id) => $q->where('customer_id', $id))
            ->orderBy('customer_id')
            ->orderBy('id');

        $rows = $query->get()->map(function (CustomerDebt $marker) {
            $partner = Customer::find($marker->customer_id);
            $hasDocuments = Invoice::where('customer_id', $marker->customer_id)->exists()
                || Purchase::where('supplier_id', $marker->customer_id)->exists()
                || CashFlow::withTrashed()->where('target_id', $marker->customer_id)->exists();
            $amount = (float) $marker->amount;

            return [
                'partner_id' => $marker->customer_id,
                'customer_id' => $marker->customer_id,
                'supplier_id' => $partner?->is_supplier ? $marker->customer_id : null,
                'merge_code' => $marker->ref_code,
                'amount' => $amount,
                'type' => $marker->type,
                'source_layer' => $marker->type === 'merge_marker' ? 'reference' : 'legacy_opening',
                'affects_debt_balance' => $marker->type !== 'merge_marker' && abs($amount) >= 0.01,
                'is_reference_only' => $marker->type === 'merge_marker',
                'debt_amount' => (float) ($partner?->debt_amount ?? 0),
                'supplier_debt_amount' => (float) ($partner?->supplier_debt_amount ?? 0),
                'customer_net_position' => (float) ($partner?->debt_amount ?? 0)
                    - (float) ($partner?->supplier_debt_amount ?? 0),
                'supplier_net_position' => (float) ($partner?->supplier_debt_amount ?? 0)
                    - (float) ($partner?->debt_amount ?? 0),
                'has_explanatory_documents' => $hasDocuments,
                'suggested_action' => abs($amount) < 0.01
                    ? 'no_action'
                    : ($hasDocuments ? 'manual_review_required' : 'missing_document_history'),
            ];
        })->values();

        if ($this->option('json')) {
            $this->line($rows->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->table([
            'Partner',
            'Merge code',
            'Amount',
            'Customer net',
            'Supplier net',
            'Documents',
            'Suggested action',
        ], $rows->map(fn (array $row) => [
            $row['partner_id'],
            $row['merge_code'],
            $row['amount'],
            $row['customer_net_position'],
            $row['supplier_net_position'],
            $row['has_explanatory_documents'] ? 'yes' : 'no',
            $row['suggested_action'],
        ])->all());

        return self::SUCCESS;
    }
}
