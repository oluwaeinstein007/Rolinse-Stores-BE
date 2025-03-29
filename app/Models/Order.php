<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    //
    protected $fillable = [
        'user_email',
        'order_number',
        'status',
        'grand_total',
        'shipping_cost',
        'grand_total_ngn',
        'item_count',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
