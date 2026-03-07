<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type_id',
        'recipient_type',
        'recipient_id',
        'warehouse_id',
        'amount',
        'note',
        'payment_method',
        'reference_number',
        'receipt_date',
        'status',
        'impact_applied',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'receipt_date' => 'date',
        'impact_applied' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function receiptType()
    {
        return $this->belongsTo(CashReceiptType::class, 'type_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    //public function customer()
    //{
        //return $this->belongsTo(Customer::class, 'recipient_id')->where('recipient_type', 'customer');
    //}

    //public function supplier()
    //{
        //return $this->belongsTo(Supplier::class, 'recipient_id')->where('recipient_type', 'supplier');
    //}

    public function transactions()
    {
        return $this->hasMany(CashReceiptTransaction::class, 'receipt_id');
    }

    public function getRecipientAttribute()
    {
        if ($this->recipient_type === 'customer') {
            return Customer::find($this->recipient_id);
        } elseif ($this->recipient_type === 'supplier') {
            return Supplier::find($this->recipient_id);
        }
        return null;
    }
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByRecipientType($query, $type)
    {
        return $query->where('recipient_type', $type);
    }

    public static function generateCode()
    {
        $lastReceipt = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastReceipt ? ($lastReceipt->id + 1) : 1;
        return 'RVN' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receipt) {
            if (empty($receipt->code)) {
                $receipt->code = self::generateCode();
            }
        });
    }
}