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
