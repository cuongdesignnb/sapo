<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'order_id',
        'order_code',
        'earned_at',
        'order_total',
        'commission_rate',
        'commission_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'earned_at' => 'date',
        'order_total' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
