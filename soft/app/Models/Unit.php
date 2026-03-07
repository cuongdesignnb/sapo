<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'name',
        'note'
    ];

    // Relationship: Unit có nhiều sản phẩm
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}