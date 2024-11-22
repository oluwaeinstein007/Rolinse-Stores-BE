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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // $table->string('name');               // Product name
            // $table->text('description');          // Product description
            // $table->decimal('price', 10, 2);      // Price of the product
            // $table->enum('gender', ['male', 'female']);  // Male or Female product
            // $table->string('category');           // Category (clothes, shoes, bags, etc.)
            // $table->string('color');              // Color of the product
            // $table->string('size')->nullable();   // Size of the product
            // $table->string('brand')->nullable();  // Brand of the product
            // $table->string('image')->nullable();  // Image URL of the product
            // $table->boolean('in_stock')->default(true);
            // $table->integer('stock');             // Available stock
            // $table->integer('sold')->default(0);  // Number of sold products
            // $table->integer('views')->default(0); // Number of views
            // $table->integer('rating')->default(0); // Rating of the product

            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('brand_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->text('description');
            $table->string('material');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
