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

            // $table->boolean('is_deal_of_month')->default(false); // For "Deal of the Month"
            // $table->string('deal_type')->nullable(); // For "Special Deals" (e.g., Christmas, Black Friday)
            // $table->foreign('special_deal_deal_type')->nullable()->constrained()->onDelete('set null');
            //reference deal_type from special_deals table
            // $table->foreign('deal_type')->references('deal_type')->on('special_deals')->onDelete('set null');
            $table->string('special_deal_slug')->nullable(); // Define the column before adding the foreign key
            $table->foreign('special_deal_slug')->references('slug')->on('special_deals')->onDelete('set null');
            $table->decimal('discount', 5, 2)->default(0.00); // Discount percentage

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
