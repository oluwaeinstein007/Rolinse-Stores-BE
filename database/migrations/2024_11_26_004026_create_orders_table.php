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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('user_email');
            $table->string('order_number')->unique();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->decimal('grand_total', 10, 2);
            $table->integer('item_count');
            $table->timestamps();

            // $table->foreign('user_email')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('user_email')->references('email')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
