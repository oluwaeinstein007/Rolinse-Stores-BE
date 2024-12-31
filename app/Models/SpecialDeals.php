<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialDeals extends Model
{
    //
    protected $fillable = [
        'deal_type',
        'slug',
        'image',
    ];
}
