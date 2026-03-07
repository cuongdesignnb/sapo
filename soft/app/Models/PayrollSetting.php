<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'pay_cycle',
        'start_day',
        'end_day',
        'start_in_prev_month',
        'pay_day',
        'default_recalculate_timekeeping',
        'auto_generate_enabled',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'start_day' => 'integer',
        'end_day' => 'integer',
        'start_in_prev_month' => 'boolean',
        'pay_day' => 'integer',
        'default_recalculate_timekeeping' => 'boolean',
        'auto_generate_enabled' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}
