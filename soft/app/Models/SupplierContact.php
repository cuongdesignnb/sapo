<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'name',
        'position',
        'phone',
        'email', 
        'department',
        'is_primary',
        'note',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the supplier
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure only one primary contact per supplier
        static::saving(function ($contact) {
            if ($contact->is_primary) {
                self::where('supplier_id', $contact->supplier_id)
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    /**
     * Get formatted phone
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        if (empty($this->phone)) {
            return null;
        }

        $phone = preg_replace('/\D/', '', $this->phone);
        
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7);
        }
        
        return $this->phone;
    }
}