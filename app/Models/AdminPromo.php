<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminPromo extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_percentage',
        'promo_code',
        'limited',
        'user_id',
        // 'product_type',
        'max_uses',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        // 'product_type' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function scopeUpdateStatus(self $query): void
    {
        $query->where('valid_from', '<', Carbon::now())
            ->update([
                'is_active' => false,
                'status' => 'expired',
            ]);
    }

    public static function updateMaxUses(int $id): void
    {
        $promo = self::findOrFail($id);

        if ($promo->limited && $promo->max_uses > 0) {
            $promo->decrement('max_uses');
            $promo->save();

            if ($promo->max_uses === 0) {
                $promo->is_active = false;
                $promo->save();
            }
        }
    }


    public function users(){
        return $this->belongsToMany(User::class, 'promo_users', 'promo_id', 'user_id')
                    ->withTimestamps(); // Tracks which users used the promo
    }

}
