<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoUser extends Model
{
    //
    protected $table = 'promo_users';
    protected $fillable = ['user_id', 'promo_id'];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function promo(){
        return $this->belongsTo(AdminPromo::class);
    }
}
