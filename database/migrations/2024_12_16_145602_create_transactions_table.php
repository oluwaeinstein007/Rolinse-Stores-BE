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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_email')->nullable();
            $table->string('reference')->unique();
            $table->string('payment_id')->unique();
            $table->string('description')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['PayPal', 'Stripe', 'Bank Transfer'])->nullable();
            $table->enum('type', ['one-off', 'recurring', 'tip', 'refund']);
            $table->enum('status', ['completed', 'rejected', 'cancelled', 'pending', 'in-escrow', 'withdrawn']);
            //deleted_at
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
