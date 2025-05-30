<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\ProductImage;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        $brands = Brand::all();
        $categories = Category::all();
        $attributes = Attribute::all();

        for ($i = 1; $i <= 100; $i++) {
            // Create a product
            $product = Product::create([
                'name' => $faker->words(3, true),
                'category_id' => $categories->random()->id,
                'brand_id' => $brands->random()->id,
                'description' => $faker->paragraph,
                'material' => $faker->randomElement(['Cotton', 'Polyester', 'Leather', 'Silk']),
                'weight' => $faker->randomFloat(2, 0.1, 0.5),
                'price' => $faker->randomFloat(2, 10, 500),
            ]);

            // Assign attributes to the product
            $product->attributes()->sync($attributes->random(rand(3, 6))->pluck('id'));

            //add images to the product
            $placeholderImages = array_diff(scandir(public_path('images/products')), ['.', '..']);

            if (empty($placeholderImages)) {
                throw new \Exception("No placeholder images found in the 'products' directory.");
            }

            for ($j = 1; $j <= rand(2, 5); $j++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => url('images/products/' . $placeholderImages[array_rand($placeholderImages)]),
                    'color_id' => $attributes->where('type', 'color')->random()->id,
                ]);
            }




        }


        $deals = [
            ['name' => "Deal of the Month"],
            ['name' => "Christmas Sale"],
            ['name' => "New Year Sale"],
            ['name' => "Black Friday Deal"],
            ['name' => "Summer Sale"],
            ['name' => "Winter Sale"],
            ['name' => "Spring Sale"],
            ['name' => "Autumn Sale"],
            ['name' => "Easter Sale"],
            ['name' => "Valentine's Day Deal"],
        ];

        //update some products with special deals
        $products = Product::inRandomOrder()->limit(15)->get();
        foreach ($products as $product) {
            $product->update([
                // 'deal_type' => $deals[rand(0, 9)]['name'],
                'special_deal_slug' => Str::slug($deals[rand(0, 9)]['name'], '_'),
                'discount' => rand(10, 100),
            ]);
        }
    }
}
