<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'parent_id', 
        'note'
    ];

    // Relationship: Category có thể có danh mục cha
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Relationship: Category có thể có nhiều danh mục con
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Relationship: Category có nhiều sản phẩm
    public function products()
    {
        return $this->hasMany(Product::class, 'category_name', 'name');
    }
}