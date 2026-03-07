<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'supplier_id',
        'warehouse_id',
        'created_by',
        'expected_at',
        'imported_at',
        'status',
        'discount',
        'additional_fee',
        'tax',
        'total',
        'need_pay',
        'paid',
        'note',
        'tags',
        'delivery_address',
        'delivery_contact',
        'delivery_phone',
        'estimated_delivery_date',
        'actual_delivery_date',
        'supplier_invoice_code',
    'internal_note',
    // NEW: Flag phân biệt đơn đặt hàng (không tạo công nợ / không nhập kho) với chứng từ thực tế
    'is_order_only',
    // (Tương lai) có thể thêm 'converted_receipt_id' nếu cần tham chiếu phiếu nhập đã tạo
    ];

    protected $casts = [
        'expected_at' => 'date',
        'imported_at' => 'datetime',
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'discount' => 'decimal:2',
        'additional_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'need_pay' => 'decimal:2',
        'paid' => 'decimal:2',
        'is_order_only' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------
    */
    public function scopeOrderOnly($query)
    {
        return $query->where('is_order_only', true);
    }

    public function scopeRealReceipts($query)
    {
        return $query->where('is_order_only', false);
    }

    /*
    |--------------------------------------------------------------
    | Accessors / Helpers
    |--------------------------------------------------------------
    */
    public function getIsPlannedAttribute(): bool
    {
        return (bool) $this->is_order_only;
    }

    public function getIsConvertibleAttribute(): bool
    {
        // Cho phép FE hiển thị nút "Tạo phiếu nhập" khi đơn đặt đã duyệt và chưa được chuyển đổi
        // (Hiện chưa có cờ đánh dấu chuyển đổi, tạm thời chỉ dựa trên is_order_only + status)
        return $this->is_order_only && in_array($this->status, ['approved']);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            'draft' => 'Nháp',
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'ordered' => 'Đã đặt hàng',
            'partial_received' => 'Nhập một phần',
            'received' => 'Đã nhập kho',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
        ];

        return $statusMap[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colorMap = [
            'draft' => 'gray',
            'pending' => 'orange',
            'approved' => 'blue',
            'ordered' => 'purple',
            'partial_received' => 'yellow',
            'received' => 'green',
            'completed' => 'green',
            'cancelled' => 'red',
        ];

        return $colorMap[$this->status] ?? 'gray';
    }
}