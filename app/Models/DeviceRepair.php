<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceRepair extends Model
{
    protected $fillable = [
        'code',
        'product_id',
        'serial_imei_id',
        'original_cost',
        'parts_cost',
        'total_cost',
        'issue_description',
        'status',
        'assigned_employee_id',
        'assigned_at',
        'completed_at',
        'branch_id',
        'notes',
        'deadline',
        'created_by',
    ];

    protected $casts = [
        'original_cost' => 'decimal:0',
        'parts_cost'    => 'decimal:0',
        'total_cost'    => 'decimal:0',
        'assigned_at'   => 'datetime',
        'completed_at'  => 'datetime',
        'deadline'      => 'date',
    ];

    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';

    const STATUS_MAP = [
        self::STATUS_PENDING     => 'Chờ xử lý',
        self::STATUS_IN_PROGRESS => 'Đang sửa',
        self::STATUS_COMPLETED   => 'Hoàn thành',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_MAP[$this->status] ?? $this->status;
    }

    // ── Relationships ──

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function serialImei()
    {
        return $this->belongsTo(SerialImei::class, 'serial_imei_id');
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function parts()
    {
        return $this->hasMany(DeviceRepairPart::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ── Helpers ──

    public function recalculateCosts(): void
    {
        $this->parts_cost = $this->parts()->sum('total_cost');
        $this->total_cost = $this->original_cost + $this->parts_cost;
        $this->save();
    }

    public static function generateCode(): string
    {
        $last = static::orderByDesc('id')->value('code');
        $num = 1;
        if ($last && preg_match('/SC-(\d+)/', $last, $m)) {
            $num = (int) $m[1] + 1;
        }
        return 'SC-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
}
