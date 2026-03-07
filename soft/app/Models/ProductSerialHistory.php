<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSerialHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_serial_id',
        'action',
        'from_warehouse_id',
        'to_warehouse_id',
        'reference_type',
        'reference_id',
        'user_id',
        'note',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== ACTION CONSTANTS ==========
    const ACTION_IMPORTED    = 'imported';
    const ACTION_SOLD        = 'sold';
    const ACTION_RETURNED    = 'returned';
    const ACTION_TRANSFERRED = 'transferred';
    const ACTION_ADJUSTED    = 'adjusted';
    const ACTION_DEFECTIVE   = 'defective';

    const ACTION_MAP = [
        self::ACTION_IMPORTED    => 'Nhập kho',
        self::ACTION_SOLD        => 'Bán hàng',
        self::ACTION_RETURNED    => 'Trả hàng',
        self::ACTION_TRANSFERRED => 'Chuyển kho',
        self::ACTION_ADJUSTED    => 'Điều chỉnh',
        self::ACTION_DEFECTIVE   => 'Lỗi/Hỏng',
    ];

    const ACTION_COLORS = [
        self::ACTION_IMPORTED    => 'bg-green-100 text-green-800',
        self::ACTION_SOLD        => 'bg-blue-100 text-blue-800',
        self::ACTION_RETURNED    => 'bg-yellow-100 text-yellow-800',
        self::ACTION_TRANSFERRED => 'bg-purple-100 text-purple-800',
        self::ACTION_ADJUSTED    => 'bg-gray-100 text-gray-800',
        self::ACTION_DEFECTIVE   => 'bg-red-100 text-red-800',
    ];

    // ========== RELATIONSHIPS ==========
    public function productSerial()
    {
        return $this->belongsTo(ProductSerial::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    // ========== ACCESSORS ==========
    public function getActionTextAttribute()
    {
        return self::ACTION_MAP[$this->action] ?? 'Không xác định';
    }

    public function getActionColorAttribute()
    {
        return self::ACTION_COLORS[$this->action] ?? 'bg-gray-100 text-gray-800';
    }
}
