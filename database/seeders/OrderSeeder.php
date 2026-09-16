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
        // Random demo data with no stable key — guard against `db:seed`
        // piling up another 10 orders (with valid-looking but ever-growing
        // random order numbers) on every re-run.
        if (DB::table('orders')->exists()) {
            return;
        }

        // Seed 10 orders
        for ($i = 0; $i < 10; $i++) {
            $userEmail = "user" . $i . "@example.com";
            $orderNumber = strtoupper(Str::random(10));
            $status = ['pending', 'completed', 'cancelled'][array_rand(['pending', 'completed', 'cancelled'])];

            // Select random products, rolling each one's quantity/total once
            // up front — previously this was rolled again (with different
            // random results) when inserting order_items below, so a seeded
            // order's grand_total/item_count never actually matched the sum
            // of its own line items.
            $products = Product::inRandomOrder()->limit(rand(1, 5))->get()->map(function ($product) {
                $product->seeded_quantity = rand(1, 5);
                $product->seeded_total_price = $product->seeded_quantity * $product->price;

                return $product;
            });

            $grandTotal = $products->sum('seeded_total_price');
            $itemCount = $products->sum('seeded_quantity');

            // Insert order
            $orderId = DB::table('orders')->insertGetId([
                'user_email' => $userEmail,
                'order_number' => $orderNumber,
                'status' => $status,
                'grand_total' => $grandTotal,
                'shipping_cost' => 0.00,
                'grand_total_ngn' => $grandTotal * 500, // Assuming a conversion rate of 500 NGN to 1 USD
                'item_count' => $itemCount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert order items
            foreach ($products as $product) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'quantity' => $product->seeded_quantity,
                    'image' => ProductImage::where('product_id', $product->id)->first()?->image_path,
                    'price_per_unit' => $product->price,
                    'total_price' => $product->seeded_total_price,
                    'currency' => 'USD',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
