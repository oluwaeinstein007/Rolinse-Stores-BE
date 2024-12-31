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
        Schema::create('special_deals', function (Blueprint $table) {
            $table->id();
            $table->string('deal_type')->nullable();
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            // $table->decimal('discount', 5, 2)->default(0.00);
            // $table->date('start_date')->nullable();
            // $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_deals');
    }
};
