<?php
// app/Models/ShippingProvider.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingLog extends Model
{
    protected $fillable = [
        'order_shipping_id', 'status', 'location', 'description', 'logged_at'
    ];

    protected $casts = [
        'logged_at' => 'datetime'
    ];

    public function orderShipping()
    {
        return $this->belongsTo(OrderShipping::class);
    }
}
