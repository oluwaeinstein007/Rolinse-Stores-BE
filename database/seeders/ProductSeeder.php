<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\ProductImage;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        $brands = Brand::all();
        $categories = Category::all();
        $attributes = Attribute::all();

        for ($i = 1; $i <= 30; $i++) {
            // Create a product
            $product = Product::create([
                'name' => $faker->words(3, true),
                'category_id' => $categories->random()->id,
                'brand_id' => $brands->random()->id,
                'description' => $faker->paragraph,
                'material' => $faker->randomElement(['Cotton', 'Polyester', 'Leather', 'Silk']),
                'price' => $faker->randomFloat(2, 10, 500),
            ]);

            // Assign attributes to the product
            $product->attributes()->sync($attributes->random(rand(3, 6))->pluck('id'));

            // Add images to the product
            for ($j = 1; $j <= rand(2, 5); $j++) {
                $imagePath = $faker->image('public/storage/products', 640, 480, null, false);

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => url('storage/products/' . $imagePath),
                    'color_id' => $attributes->where('type', 'color')->random()->id,
                ]);
            }

        }
    }
}
