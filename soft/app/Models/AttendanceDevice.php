<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'name',
        'model',
        'serial_number',
        'ip_address',
        'tcp_port',
        'comm_key',
        'status',
        'notes',
        'last_sync_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
