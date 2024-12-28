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
        'item_count',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
