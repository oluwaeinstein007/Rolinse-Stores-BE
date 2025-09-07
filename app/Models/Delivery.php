<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'recipientAddress',
        'recipientState',
        'recipientName',
        'recipientPhone',
        'recipientEmail',
        'weight',
        'pickup_state',
        'email',
        'uniqueID',
        'CustToken',
        'BatchID',
        'valueOfItem',
        'delivery_order_id',
        'delivery_status',
        'is_nigeria',
        'is_benin',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
