<?php

// app/Models/Order.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'customer_id',
        'warehouse_id',
        'cashier_id',
        'session_id',
        'total',
        'subtotal',           
        'discount_percent',   
        'discount_amount',  
        'vat_percent',      
        'vat_amount',
        'paid',
        'debt',
        'status',
        'source',
        'priority',
        'delivery_address',
        'delivery_phone',
        'delivery_contact',
        'tags',
        'branch_id',
        'created_by',
        'ordered_at',
        'note',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2', 
        'discount_percent' => 'decimal:2', 
        'discount_amount' => 'decimal:2', 
        'vat_percent' => 'decimal:2',       
        'vat_amount' => 'decimal:2', 
        'paid' => 'decimal:2',
        'debt' => 'decimal:2',
        'ordered_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at', 'desc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('paid_at', 'desc');
    }

    public function shipping(): HasOne
    {
        return $this->hasOne(OrderShipping::class);
    }

    public function customerDebt(): HasOne
    {
        return $this->hasOne(CustomerDebt::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /**
     * Scopes
     */
    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Accessors
     */
    public function getStatusTextAttribute()
    {
        $statusMap = [
            // Quy trình cũ (tương thích ngược)
            'pending' => 'Chờ xử lý',
            'confirmed' => 'Đã xác nhận',
            'processing' => 'Đang xử lý',
            'shipping' => 'Đang giao hàng',
            'delivered' => 'Đã giao hàng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            'refunded' => 'Đã hoàn tiền',
            
            // Quy trình mới 5 bước
            'ordered' => 'Đặt hàng',
            'approved' => 'Đã duyệt',
            'shipping_created' => 'Đã tạo vận chuyển',
            
            // Trạng thái trả hàng
            'partially_returned' => 'Trả hàng một phần',
            'returned' => 'Đã trả hàng',
        ];

        return $statusMap[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colorMap = [
            // Quy trình cũ
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'processing' => 'orange',
            'shipping' => 'purple',
            'delivered' => 'green',
            'completed' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray',
            
            // Quy trình mới 5 bước
            'ordered' => 'yellow',         // Bước 1: Đặt hàng (vàng)
            'approved' => 'blue',          // Bước 2: Đã duyệt (xanh dương)
            'shipping_created' => 'purple', // Bước 3: Đã tạo vận chuyển (tím)
            // 'delivered' => 'green',      // Bước 4: Đã giao (xanh lá) - dùng chung
            // 'completed' => 'green',      // Bước 5: Hoàn thành (xanh lá) - dùng chung
            
            // Trạng thái trả hàng
            'partially_returned' => 'orange',  // Trả hàng một phần (cam)
            'returned' => 'gray',              // Đã trả hàng (xám)
        ];

        return $colorMap[$this->status] ?? 'gray';
    }

    public function getTotalQuantityAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getTotalProfitAttribute()
    {
        return $this->items->sum('profit');
    }

    /**
     * Methods
     */
    public function canEdit()
    {
        // Quy trình mới: chỉ có thể sửa khi vừa tạo đơn
        return in_array($this->status, ['pending', 'ordered']);
    }

    public function canDelete()
    {
        // Chỉ có thể xóa đơn chưa duyệt hoặc đã hủy
        return in_array($this->status, ['pending', 'ordered', 'cancelled']);
    }

    public function canCancel()
    {
        // Có thể hủy đơn từ khi tạo đến khi chưa xuất kho
        return in_array($this->status, ['pending', 'confirmed', 'ordered', 'approved', 'shipping_created']);
    }

    public function canApprove()
    {
        return $this->status === 'ordered';
    }

    public function canCreateShipping()
    {
        return $this->status === 'approved';
    }

    public function canExportStock()
    {
        return in_array($this->status, ['approved', 'shipping_created']);
    }

    public function canPayment()
    {
        return $this->status === 'delivered' && $this->debt > 0;
    }

    public function canCreateReturn()
    {
        // Chỉ có thể tạo đơn trả hàng từ các đơn đã hoàn thành hoặc đã giao
        return in_array($this->status, ['completed', 'delivered']);
    }

    public function hasReturns()
    {
        return $this->returns()->exists();
    }

    public function getTotalReturnedQuantityAttribute()
    {
        return $this->returns()
            ->where('status', '!=', 'cancelled')
            ->with('items')
            ->get()
            ->sum(function($return) {
                return $return->items->sum('quantity');
            });
    }

    public function updateReturnStatus()
    {
        $totalQuantity = $this->total_quantity;
        $returnedQuantity = $this->total_returned_quantity;
        
        if ($returnedQuantity == 0) {
            // Không có trả hàng, giữ nguyên status hiện tại
            return;
        } elseif ($returnedQuantity >= $totalQuantity) {
            // Trả hàng toàn bộ
            $this->updateStatus('returned', 'Đơn hàng đã được trả hàng toàn bộ');
        } else {
            // Trả hàng một phần
            $this->updateStatus('partially_returned', 'Đơn hàng đã được trả hàng một phần');
        }
    }

    public function updateStatus($newStatus, $note = null, $userId = null)
    {
        $oldStatus = $this->status;
        
        $this->update(['status' => $newStatus]);

        // Tạo history record
        OrderStatusHistory::create([
            'order_id' => $this->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'note' => $note,
            'changed_by' => $userId ?? auth()->id(),
            'changed_at' => now(),
        ]);

        return $this;
    }
}

// app/Models/OrderStatusHistory.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';
    public $timestamps = false;
    
    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'note',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

// app/Models/OrderPayment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'transaction_id',
        'bank_account',
        'note',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accessors
     */
    public function getPaymentMethodTextAttribute()
    {
        $methodMap = [
            'cash' => 'Tiền mặt',
            'transfer' => 'Chuyển khoản',
            'card' => 'Thẻ tín dụng',
            'wallet' => 'Ví điện tử',
            'other' => 'Khác',
        ];

        return $methodMap[$this->payment_method] ?? $this->payment_method;
    }
}
