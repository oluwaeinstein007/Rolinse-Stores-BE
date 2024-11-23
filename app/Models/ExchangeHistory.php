<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ExchangeHistory extends Model
{
    //
    protected $fillable = [
        // 'user_id',
        'currencyCode',
        'rate',
        'created_at',
    ];

    public function admin(){
        return $this->belongsTo(User::class, 'user_id');
    }
}
