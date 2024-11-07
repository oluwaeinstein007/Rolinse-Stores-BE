<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    protected $primaryKey = 'user_id';
    protected $fillable = [
        'user_id', 'type', 'sub_type', 'title', 'body', 'status', 'link',
    ];

    // Define the user relationship
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
