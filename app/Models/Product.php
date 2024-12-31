<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    // protected $fillable = [
    //     'name',
    //     'description',
    //     'price',
    //     'gender',
    //     'category',
    //     'color',
    //     'stock'
    // ];
    protected $fillable = ['category_id', 'brand_id', 'name', 'description', 'material', 'price', 'deal_type', 'discount'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }


    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes', 'product_id', 'attribute_id')
                    ->withTimestamps();
    }


    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
