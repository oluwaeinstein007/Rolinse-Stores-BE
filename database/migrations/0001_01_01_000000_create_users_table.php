<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender')->nullable();
            $table->string('date_of_birth')->nullable();

            $table->string('country');
            $table->string('postal_code')->nullable();
            $table->string('address')->nullable();
            $table->string('phone_number');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            $table->string('referral_code')->nullable();
            $table->string('referral_link')->nullable();
            $table->string('referral_count')->default(0);
            $table->string('referral_by')->nullable();

            $table->string('social_type')->nullable();
            $table->boolean('is_social')->default(0);

            $table->string('status')->default('active');
            $table->boolean('is_suspended')->default(0);
            $table->string('suspension_reason')->nullable();
            $table->timestamp('suspension_date')->nullable();
            $table->tinyInteger('suspension_duration')->nullable();

            $table->tinyInteger('user_role_id')->default(2);  // Default to Regular User
            $table->integer('auth_otp')->nullable();
            $table->timestamp('auth_otp_expires_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
