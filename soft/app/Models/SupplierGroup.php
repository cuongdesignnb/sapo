<?php

// =================================================================
// Model 1: SupplierGroup
// File: app/Models/SupplierGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name', 
        'type',
        'description',
        'discount_percent',
        'payment_terms',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'payment_terms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get suppliers in this group
     */
    public function suppliers()
    {
        return $this->hasMany(Supplier::class, 'group_id');
    }

    /**
     * Generate next group code
     */
    public static function generateCode(): string
    {
        $lastGroup = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastGroup ? ($lastGroup->id + 1) : 1;
        
        return 'GRP' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($group) {
            if (empty($group->code)) {
                $group->code = self::generateCode();
            }
        });
    }
}