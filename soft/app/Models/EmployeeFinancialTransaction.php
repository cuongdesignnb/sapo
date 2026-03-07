<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeFinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'warehouse_id',
        'occurred_at',
        'type',
        'amount',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
