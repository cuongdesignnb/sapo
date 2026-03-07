<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class CustomerDebt extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'order_id',
        'ref_code',
        'amount',
        'debt_total',
        'note',
        'created_by',
        'recorded_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'debt_total' => 'decimal:2',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $dates = [
        'recorded_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Boot method - Auto calculate debt_total and generate ref_code
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customerDebt) {
            // Auto generate ref_code if not provided
            if (empty($customerDebt->ref_code)) {
                $customerDebt->ref_code = 'CD' . now()->format('YmdHis') . rand(100, 999);
            }

            // Set recorded_at to now if not provided
            if (empty($customerDebt->recorded_at)) {
                $customerDebt->recorded_at = now();
            }

            // Auto calculate debt_total
            $customerDebt->calculateDebtTotal();
        });

        static::updating(function ($customerDebt) {
            // Recalculate debt_total if amount changed
            if ($customerDebt->isDirty('amount')) {
                $customerDebt->calculateDebtTotal();
            }
        });

        static::created(function ($customerDebt) {
            // Update customer total_debt
            $customerDebt->updateCustomerTotalDebt();
        });

        static::updated(function ($customerDebt) {
            // Update customer total_debt
            $customerDebt->updateCustomerTotalDebt();
        });

        static::deleted(function ($customerDebt) {
            // Update customer total_debt
            $customerDebt->updateCustomerTotalDebt();
        });
    }

    /**
     * Relationship with Customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relationship with Order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relationship with User (creator)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Filter by customer
     */
    public function scopeByCustomer(Builder $query, $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeByDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter by debt type (positive = debt, negative = payment)
     */
    public function scopeByType(Builder $query, $type): Builder
    {
        if ($type === 'debt') {
            return $query->where('amount', '>', 0);
        } elseif ($type === 'payment') {
            return $query->where('amount', '<', 0);
        }
        return $query;
    }

    /**
     * Scope: Order by recorded date desc
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    /**
     * Scope: With customer info
     */
    public function scopeWithCustomer(Builder $query): Builder
    {
        return $query->with(['customer:id,code,name,email,phone,total_debt']);
    }

    /**
     * Scope: With order info
     */
    public function scopeWithOrder(Builder $query): Builder
    {
        return $query->with(['order:id,code,total,status']);
    }

    /**
     * Scope: With creator info
     */
    public function scopeWithCreator(Builder $query): Builder
    {
        return $query->with(['creator:id,name,email']);
    }

    /**
     * Calculate debt_total based on previous debt records
     */
    public function calculateDebtTotal()
    {
        // Get previous debt total for this customer
        $previousDebt = static::where('customer_id', $this->customer_id)
            ->where('recorded_at', '<', $this->recorded_at ?? now())
            ->when($this->exists, function ($query) {
                $query->where('id', '!=', $this->id);
            })
            ->orderBy('recorded_at', 'desc')
            ->first();

        $previousTotal = $previousDebt ? $previousDebt->debt_total : 0;
        $this->debt_total = $previousTotal + $this->amount;
    }

    /**
     * Update customer's total_debt field
     */
    public function updateCustomerTotalDebt()
    {
        if ($this->customer_id) {
            $totalDebt = static::where('customer_id', $this->customer_id)
                ->orderBy('recorded_at', 'desc')
                ->first();

            Customer::where('id', $this->customer_id)
                ->update(['total_debt' => $totalDebt ? $totalDebt->debt_total : 0]);
        }
    }

    /**
     * Check if this is a debt transaction (positive amount)
     */
    public function isDebt(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if this is a payment transaction (negative amount)
     */
    public function isPayment(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Get transaction type as string
     */
    public function getTypeAttribute(): string
    {
        return $this->isDebt() ? 'debt' : 'payment';
    }

    /**
     * Get formatted amount with proper sign
     */
    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->isDebt() ? '+' : '';
        return $prefix . number_format($this->amount, 0, ',', '.') . ' VNĐ';
    }

    /**
     * Get formatted debt total
     */
    public function getFormattedDebtTotalAttribute(): string
    {
        return number_format($this->debt_total, 0, ',', '.') . ' VNĐ';
    }

    /**
     * Get debt status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return $this->isDebt() ? 'text-red-600' : 'text-green-600';
    }

    /**
     * Get debt status badge color
     */
    public function getBadgeColorAttribute(): string
    {
        return $this->isDebt() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
    }

    /**
     * Static method: Get debt summary for a customer
     */
    public static function getCustomerDebtSummary($customerId)
    {
        $debts = static::where('customer_id', $customerId)
            ->selectRaw('
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debt,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_paid,
                COUNT(CASE WHEN amount > 0 THEN 1 END) as debt_count,
                COUNT(CASE WHEN amount < 0 THEN 1 END) as payment_count
            ')
            ->first();

        $latestRecord = static::where('customer_id', $customerId)
            ->orderBy('recorded_at', 'desc')
            ->first();

        return [
            'total_debt' => $debts->total_debt ?? 0,
            'total_paid' => $debts->total_paid ?? 0,
            'current_balance' => $latestRecord ? $latestRecord->debt_total : 0,
            'debt_transactions' => $debts->debt_count ?? 0,
            'payment_transactions' => $debts->payment_count ?? 0,
            'last_transaction_date' => $latestRecord ? $latestRecord->recorded_at : null
        ];
    }

    /**
     * Static method: Get top debtors
     */
    public static function getTopDebtors($limit = 10)
    {
        return static::select('customer_id')
            ->selectRaw('MAX(debt_total) as current_debt')
            ->with(['customer:id,code,name,email,phone'])
            ->groupBy('customer_id')
            ->having('current_debt', '>', 0)
            ->orderBy('current_debt', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Static method: Create payment transaction
     */
    public static function createPayment($customerId, $amount, $note = null, $refCode = null)
    {
        return static::create([
            'customer_id' => $customerId,
            'amount' => -abs($amount), // Ensure negative for payment
            'note' => $note ?? 'Thanh toán',
            'ref_code' => $refCode,
            'created_by' => auth()->id(),
            'recorded_at' => now()
        ]);
    }

    /**
     * Static method: Create debt adjustment
     */
    public static function createAdjustment($customerId, $amount, $note, $refCode = null)
    {
        return static::create([
            'customer_id' => $customerId,
            'amount' => $amount,
            'note' => $note ?? 'Điều chỉnh công nợ',
            'ref_code' => $refCode,
            'created_by' => auth()->id(),
            'recorded_at' => now()
        ]);
    }
}