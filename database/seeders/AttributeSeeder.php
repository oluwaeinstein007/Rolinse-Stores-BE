<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attribute;

class AttributeSeeder extends Seeder
{
    public function run()
    {
        $attributes = [
            ['type' => 'color', 'value' => 'Red'],
            ['type' => 'color', 'value' => 'Blue'],
            ['type' => 'color', 'value' => 'Black'],
            ['type' => 'color', 'value' => 'White'],
            ['type' => 'color', 'value' => 'Green'],
            ['type' => 'color', 'value' => 'Yellow'],
            ['type' => 'color', 'value' => 'Purple'],
            ['type' => 'color', 'value' => 'Orange'],
            ['type' => 'color', 'value' => 'Pink'],
            ['type' => 'color', 'value' => 'Brown'],
            ['type' => 'color', 'value' => 'Grey'],
            ['type' => 'color', 'value' => 'Beige'],
            ['type' => 'color', 'value' => 'Gold'],
            ['type' => 'color', 'value' => 'Silver'],
            // ['type' => 'color', 'value' => 'Multi'],
            ['type' => 'size', 'value' => 'XS'],
            ['type' => 'size', 'value' => 'S'],
            ['type' => 'size', 'value' => 'M'],
            ['type' => 'size', 'value' => 'L'],
            ['type' => 'size', 'value' => 'XL'],
            ['type' => 'size', 'value' => 'XXL'],
            ['type' => 'size', 'value' => 'One Size'],
            // ['type' => 'gender', 'value' => 'Male'],
            // ['type' => 'gender', 'value' => 'Female'],
            // ['type' => 'category', 'value' => 'Shirt'],
            // ['type' => 'category', 'value' => 'Pants'],
            // ['type' => 'category', 'value' => 'Shoes'],
            // ['type' => 'category', 'value' => 'Hat'],
            ['type' => 'material', 'value' => 'Cotton'],
            ['type' => 'material', 'value' => 'Polyester'],
            ['type' => 'material', 'value' => 'Wool'],
            ['type' => 'material', 'value' => 'Silk'],

        ];

        foreach ($attributes as $attribute) {
            Attribute::updateOrCreate(
                ['type' => $attribute['type'], 'value' => $attribute['value']],
                $attribute
            );
        }
    }
}

