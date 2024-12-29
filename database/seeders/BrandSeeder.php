<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Brand;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run()
    {
        $brands = [
            ['name' => 'Nike', 'image' => asset('storage/Brand/nike.jpg')],
            ['name' => 'Adidas', 'image' => asset('storage/Brand/addidas.jpg')],
            ['name' => 'Gucci', 'image' => asset('storage/Brand/Gucci.jpg')],
            ['name' => 'Zara', 'image' => asset('storage/Brand/zara.jpg')],
            ['name' => 'EA7', 'image' => asset('storage/Brand/Ea7.jpg')],
            ['name' => 'The North Face', 'image' => asset('storage/Brand/north_face.jpg')],
            ['name' => 'New Era', 'image' => asset('storage/Brand/new_era.jpg')],
            ['name' => 'BOSS', 'image' => asset('storage/Brand/Boss.jpg')],
            ['name' => 'Lacoste', 'image' => asset('storage/Brand/lacoste.jpg')],
            ['name' => 'Under Armour', 'image' => asset('storage/Brand/nike.jpg')],
            // ['name' => 'Under Armour', 'image' => 'https://via.placeholder.com/150?text=Under+Armour'],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['name' => $brand['name']],
                [
                    'name' => $brand['name'],
                    'slug' => Str::slug($brand['name'], '-'),
                    'image' => $brand['image'],
                ]
            );
        }
    }
}
