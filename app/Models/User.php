<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth',
        'country',
        'postal_code',
        'address',
        'gender',
        'referral_by',
        'referral_code',
        'referral_link',
        'referral_count',
        'social_type',
        'is_social',
        'status',
        'is_suspended',
        'suspension_reason',
        'suspension_date',
        'suspension_duration',
        'user_role_id',
        'auth_otp',
        'auth_otp_expires_at',
        'phone_number',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(UserRole::class, 'user_role_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // public function promos(){
    //     return $this->belongsToMany(AdminPromo::class, 'promo_user')
    //                 ->withTimestamps(); // Tracks when the promo was used
    // }

    public function promos(){
        return $this->belongsToMany(AdminPromo::class, 'promo_users', 'promo_id', 'user_id')
                    ->withTimestamps(); // Tracks which users used the promo
    }

    public function transactions() : HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function shippingAddresses()
    {
        return $this->hasMany(ShippingAddress::class);
    }

}
