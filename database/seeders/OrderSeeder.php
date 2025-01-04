<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ProductImage;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed 10 orders
        for ($i = 0; $i < 10; $i++) {
            $userEmail = "user" . $i . "@example.com";
            $orderNumber = strtoupper(Str::random(10));
            $status = ['pending', 'completed', 'cancelled'][array_rand(['pending', 'completed', 'cancelled'])];

            // Select random products
            $products = Product::inRandomOrder()->limit(rand(1, 5))->get();

            $grandTotal = 0;
            $itemCount = 0;

            // Calculate order totals
            foreach ($products as $product) {
                $quantity = rand(1, 5);
                $pricePerUnit = $product->price;
                $totalPrice = $quantity * $pricePerUnit;

                $grandTotal += $totalPrice;
                $itemCount += $quantity;
            }

            // Insert order
            $orderId = DB::table('orders')->insertGetId([
                'user_email' => $userEmail,
                'order_number' => $orderNumber,
                'status' => $status,
                'grand_total' => $grandTotal,
                'item_count' => $itemCount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert order items
            foreach ($products as $product) {
                $quantity = rand(1, 5);
                $pricePerUnit = $product->price;
                $totalPrice = $quantity * $pricePerUnit;

                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    // 'image' => $product->image ?? null,
                    // 'image' => ProductImage::where('product_id', $product->id)->first()->image_path ?? null,
                    'price_per_unit' => $pricePerUnit,
                    'total_price' => $totalPrice,
                    'currency' => 'USD',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
