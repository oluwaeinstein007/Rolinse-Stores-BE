<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Attribute;

class ProductAttributeSeeder extends Seeder
{
    public function run()
    {
        $product = Product::first(); // Example: Get a sample product
        $attributes = Attribute::whereIn('id', [1, 2, 3, 4, 5, 6])->get(); // Example: Select attributes by ID

        if ($product && $attributes->isNotEmpty()) {
            // Sync attributes to the product
            $product->attributes()->sync($attributes->pluck('id'));
        }
    }
}

