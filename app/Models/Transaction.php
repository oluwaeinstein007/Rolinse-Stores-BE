<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\TransactionType;
use App\Enums\TransactionStatus;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_email',
        'order_id',
        'amount',
        'payment_type',
        'type',
        'payment_method',
        'status',
        'reference',
        'description',
        'payment_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'payment_type' => TransactionType::class,
        'status' => TransactionStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($transaction) {
            $transaction->reference = strtoupper('TR' . now()->timestamp . bin2hex(random_bytes(2)));
        });
    }

    // public function refund(): HasMany
    // {
    //     return $this->hasMany(Refund::class);
    // }

    /**
     * user
     *
     * @return void
     */
    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}

