<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CustomerGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name', 
        'type',
        'description',
        'discount_percent',
        'payment_terms'
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'payment_terms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'type_text',
        'customers_count',
        'formatted_discount'
    ];

    // Constants for types
    const TYPE_VIP = 'vip';
    const TYPE_NORMAL = 'normal';
    const TYPE_LOCAL = 'local';
    const TYPE_IMPORT = 'import';

    const TYPES = [
        self::TYPE_VIP => 'VIP',
        self::TYPE_NORMAL => 'Thường',
        self::TYPE_LOCAL => 'Địa phương',
        self::TYPE_IMPORT => 'Xuất nhập khẩu'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customerGroup) {
            if (empty($customerGroup->code)) {
                $customerGroup->code = self::generateCode();
            }
        });
    }

    /**
     * Generate unique code for customer group
     */
    public static function generateCode(): string
    {
        $prefix = 'CG';
        $lastGroup = self::where('code', 'like', $prefix . '%')
                         ->orderBy('code', 'desc')
                         ->first();

        if ($lastGroup) {
            $lastNumber = (int) substr($lastGroup->code, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Relationships
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'group_id');
    }

    /**
     * Scopes
     */
    public function scopeByType(Builder $query, $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('customers');
    }

    public function scopeSearch(Builder $query, $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Accessors
     */
    public function getTypeTextAttribute(): string
    {
        return self::TYPES[$this->type] ?? 'Không xác định';
    }

    public function getCustomersCountAttribute(): int
    {
        return $this->customers()->count();
    }

    public function getFormattedDiscountAttribute(): string
    {
        return number_format($this->discount_percent, 1) . '%';
    }

    public function getTypeColorClassAttribute(): string
    {
        $colors = [
            self::TYPE_VIP => 'bg-purple-100 text-purple-800',
            self::TYPE_NORMAL => 'bg-blue-100 text-blue-800',
            self::TYPE_LOCAL => 'bg-green-100 text-green-800',
            self::TYPE_IMPORT => 'bg-orange-100 text-orange-800'
        ];

        return $colors[$this->type] ?? 'bg-gray-100 text-gray-800';
    }

    public function getFullInfoAttribute(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'type_text' => $this->type_text,
            'type_color_class' => $this->type_color_class,
            'description' => $this->description,
            'discount_percent' => $this->discount_percent,
            'formatted_discount' => $this->formatted_discount,
            'payment_terms' => $this->payment_terms,
            'customers_count' => $this->customers_count,
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Business Logic Methods
     */
    public function hasCustomers(): bool
    {
        return $this->customers()->exists();
    }

    public function canBeDeleted(): bool
    {
        return !$this->hasCustomers();
    }

    public function getTotalCustomersValue(): float
    {
        return $this->customers()->sum('total_spend');
    }

    public function getActiveCustomersCount(): int
    {
        return $this->customers()->where('status', 'active')->count();
    }

    /**
     * Static helper methods
     */
    public static function getTypesForSelect(): array
    {
        return collect(self::TYPES)->map(function ($label, $value) {
            return [
                'value' => $value,
                'label' => $label
            ];
        })->values()->toArray();
    }

    public static function getStatistics(): array
    {
        return [
            'total_groups' => self::count(),
            'vip_groups' => self::byType(self::TYPE_VIP)->count(),
            'normal_groups' => self::byType(self::TYPE_NORMAL)->count(),
            'local_groups' => self::byType(self::TYPE_LOCAL)->count(),
            'import_groups' => self::byType(self::TYPE_IMPORT)->count(),
            'active_groups' => self::active()->count(),
            'total_customers' => \App\Models\Customer::whereNotNull('group_id')->count(),
            'avg_discount' => self::avg('discount_percent'),
            'groups_with_customers' => self::whereHas('customers')->count(),
        ];
    }

    /**
     * Validation rules
     */
    public static function validationRules($id = null): array
    {
        $uniqueRule = $id ? "unique:customer_groups,code,{$id}" : 'unique:customer_groups,code';
        
        return [
            'code' => ['nullable', 'string', 'max:255', $uniqueRule],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(self::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'code.unique' => 'Mã nhóm khách hàng đã tồn tại',
            'name.required' => 'Tên nhóm khách hàng là bắt buộc',
            'name.max' => 'Tên nhóm khách hàng không được vượt quá 255 ký tự',
            'type.required' => 'Loại nhóm là bắt buộc',
            'type.in' => 'Loại nhóm không hợp lệ',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự',
            'discount_percent.numeric' => 'Chiết khấu phải là số',
            'discount_percent.min' => 'Chiết khấu không được âm',
            'discount_percent.max' => 'Chiết khấu không được vượt quá 100%',
            'payment_terms.integer' => 'Điều kiện thanh toán phải là số nguyên',
            'payment_terms.min' => 'Điều kiện thanh toán không được âm',
        ];
    }
}