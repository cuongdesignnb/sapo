<?php
// app/Models/ShippingProvider.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingProvider extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'api_config', 'pricing_config', 'status'
    ];

    protected $casts = [
        'api_config' => 'array',
        'pricing_config' => 'array'
    ];

    public function orderShippings()
    {
        return $this->hasMany(OrderShipping::class, 'provider_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}