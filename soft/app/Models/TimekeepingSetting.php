<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimekeepingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'standard_hours_per_day',
        'use_shift_allowances',
        'late_grace_minutes',
        'early_grace_minutes',
        'allow_multiple_shifts_one_inout',
        'enforce_shift_checkin_window',
        'ot_rounding_minutes',
        'ot_after_minutes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'standard_hours_per_day' => 'decimal:2',
        'use_shift_allowances' => 'boolean',
        'late_grace_minutes' => 'integer',
        'early_grace_minutes' => 'integer',
        'allow_multiple_shifts_one_inout' => 'boolean',
        'enforce_shift_checkin_window' => 'boolean',
        'ot_rounding_minutes' => 'integer',
        'ot_after_minutes' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}
