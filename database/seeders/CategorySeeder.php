<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = ['Men’s Clothing', 'Women’s Shoes', 'Accessories', 'Kids Wear'];

        foreach ($categories as $category) {
            Category::updateOrCreate(['name' => $category], ['name' => $category]);
        }
    }
}

