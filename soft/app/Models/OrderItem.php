<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'discount_percent',   // THÊM CHO POS
        'discount_amount',    // THÊM CHO POS
        'total',
        'sku',
        'product_name',
        'unit_name',
        'cost_price',
        'profit',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',  // THÊM CHO POS
        'discount_amount' => 'decimal:2',   // THÊM CHO POS
        'total' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Accessors
     */
    public function getProfitMarginAttribute()
    {
        if ($this->cost_price > 0) {
            return (($this->price - $this->cost_price) / $this->cost_price) * 100;
        }
        return 0;
    }
}