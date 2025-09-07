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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id'); // Foreign key to orders table
            $table->string('recipientAddress');
            $table->string('recipientState');
            $table->string('recipientName');
            $table->string('recipientPhone');
            $table->string('recipientEmail')->nullable(); // Make it nullable if it can be null
            $table->decimal('weight', 8, 2); // Adjust precision and scale as needed
            $table->string('pickup_state');
            $table->string('email');
            $table->string('uniqueID')->unique();
            $table->string('CustToken')->unique();
            $table->string('BatchID')->unique();
            $table->decimal('valueOfItem', 15, 2); // Adjust precision and scale as needed
            $table->string('delivery_order_id')->nullable(); // Store the order ID from the external delivery service
            $table->string('delivery_status')->default('pending'); // Default status can be 'pending', 'shipped', 'delivered', etc.
            $table->boolean('is_nigeria')->default(true); // Assuming this is a boolean field
            $table->boolean('is_benin')->default(false); // Assuming this is a boolean field
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade'); // Assuming you have an orders table
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
