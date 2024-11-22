<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductAttribute extends Pivot
{
    protected $table = 'product_attributes';
    protected $fillable = ['product_id', 'attribute_id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    // public $timestamps = true; // Ensure timestamps are used for this pivot table
}
