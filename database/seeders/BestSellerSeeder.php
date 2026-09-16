<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BestSeller;
use App\Models\Product;

class BestSellerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // This is just bootstrap/demo data for local dev — the real
        // computation (from actual order_items) already exists and
        // self-refreshes monthly in ProductController::bestSeller(), which
        // truncates and repopulates this table for real once live order data
        // exists. Just guard the random seed data itself against duplicating
        // on every `db:seed` re-run before that first real refresh happens.
        if (BestSeller::exists()) {
            return;
        }

        // Fetch top 10 products by orders count (mocked here for simplicity)
        $products = Product::inRandomOrder()->limit(10)->get();

        foreach ($products as $product) {
            BestSeller::create([
                'product_id' => $product->id,
                'orders_count' => rand(10, 100), // Random orders count for testing
            ]);
        }
    }
}
