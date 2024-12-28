<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BestSeller extends Model
{
    //
    protected $fillable = [
        'product_id',
        'orders_count',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
