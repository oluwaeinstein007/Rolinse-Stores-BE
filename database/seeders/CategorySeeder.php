<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => "Men’s Clothing", 'image' => asset('storage/Categories/men_clothing.jpg')],
            ['name' => "Men's Footwear", 'image' => asset('storage/Categories/men_footwear.jpg')],
            ['name' => "Men's Accessories", 'image' => asset('storage/Categories/men_accessories.jpg')],
            ['name' => "Women’s Clothing", 'image' => asset('storage/Categories/women_clothing.jpg')],
            ['name' => "Women’s Footwear", 'image' => asset('storage/Categories/women_footwear.jpg')],
            ['name' => "Women’s Accessories", 'image' => asset('storage/Categories/women_accessories.jpg')],
            ['name' => 'Kids Clothing', 'image' => asset('storage/Categories/kids_clothing.jpg')],
            ['name' => 'Kids Footwear', 'image' => asset('storage/Categories/kids_footwear.jpg')],
            ['name' => 'Kids Accessories', 'image' => asset('storage/Categories/kids_accessories.jpg')],
            ['name' => 'Sportswear', 'image' => asset('storage/Categories/sportswear.jpg')],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [
                    'name' => $category['name'],
                    'slug' => Str::slug($category['name'], '-'),
                    'image' => $category['image'],
                ]
            );
        }
    }
}

