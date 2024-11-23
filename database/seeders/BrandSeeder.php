<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run()
    {
        $brands = ['Nike', 'Adidas', 'Gucci', 'Zara', 'EA7', 'The North Face', 'New Era', 'BOSS', 'Lacoste', 'Under Armour'];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(['name' => $brand], ['name' => $brand]);
        }
    }
}
