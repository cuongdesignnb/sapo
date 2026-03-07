<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaysheetPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'paysheet_id',
        'payslip_id',
        'employee_id',
        'amount',
        'method',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function paysheet()
    {
        return $this->belongsTo(Paysheet::class);
    }

    public function payslip()
    {
        return $this->belongsTo(Payslip::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
