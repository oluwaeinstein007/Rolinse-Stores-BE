<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = ["Men’s Clothing", "Men's Footwear", "Men's Accessories" , "Women’s Clothing", "Women’s Footwear", "Women’s Accessories", 'Kids Clothing', 'Kids Footwear', 'Kids Accessories', 'Sportswear',];

        foreach ($categories as $category) {
            Category::updateOrCreate(['name' => $category], ['name' => $category]);
        }
    }
}

