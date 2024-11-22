<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run()
    {
        $brands = ['Nike', 'Adidas', 'Gucci', 'Zara'];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(['name' => $brand], ['name' => $brand]);
        }
    }
}

