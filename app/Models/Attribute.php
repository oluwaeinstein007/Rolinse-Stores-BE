<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $fillable = ['type', 'value', 'hex_code'];

    // public function products()
    // {
    //     return $this->belongsToMany(Product::class);
    // }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attributes', 'attribute_id', 'product_id')
            ->withTimestamps();
    }
}
