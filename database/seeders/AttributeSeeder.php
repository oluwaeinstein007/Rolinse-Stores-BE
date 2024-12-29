<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attribute;

class AttributeSeeder extends Seeder
{
    public function run()
    {
        // $attributes = [
        //     ['type' => 'color', 'value' => 'Red'],
        //     ['type' => 'color', 'value' => 'Blue'],
        //     ['type' => 'color', 'value' => 'Black'],
        //     ['type' => 'color', 'value' => 'White'],
        //     ['type' => 'color', 'value' => 'Green'],
        //     ['type' => 'color', 'value' => 'Yellow'],
        //     ['type' => 'color', 'value' => 'Purple'],
        //     ['type' => 'color', 'value' => 'Orange'],
        //     ['type' => 'color', 'value' => 'Pink'],
        //     ['type' => 'color', 'value' => 'Brown'],
        //     ['type' => 'color', 'value' => 'Grey'],
        //     ['type' => 'color', 'value' => 'Beige'],
        //     ['type' => 'color', 'value' => 'Gold'],
        //     ['type' => 'color', 'value' => 'Silver'],
        //     ['type' => 'size', 'value' => 'XS'],
        //     ['type' => 'size', 'value' => 'S'],
        //     ['type' => 'size', 'value' => 'M'],
        //     ['type' => 'size', 'value' => 'L'],
        //     ['type' => 'size', 'value' => 'XL'],
        //     ['type' => 'size', 'value' => 'XXL'],
        //     ['type' => 'size', 'value' => 'One Size'],
        //     ['type' => 'material', 'value' => 'Cotton'],
        //     ['type' => 'material', 'value' => 'Polyester'],
        //     ['type' => 'material', 'value' => 'Wool'],
        //     ['type' => 'material', 'value' => 'Silk'],
        // ];

        $attributes = [
            ['type' => 'color', 'value' => 'Red', 'hex_code' => '#FF0000'],
            ['type' => 'color', 'value' => 'Blue', 'hex_code' => '#0000FF'],
            ['type' => 'color', 'value' => 'Black', 'hex_code' => '#000000'],
            ['type' => 'color', 'value' => 'White', 'hex_code' => '#FFFFFF'],
            ['type' => 'color', 'value' => 'Green', 'hex_code' => '#008000'],
            ['type' => 'color', 'value' => 'Yellow', 'hex_code' => '#FFFF00'],
            ['type' => 'color', 'value' => 'Purple', 'hex_code' => '#800080'],
            ['type' => 'color', 'value' => 'Orange', 'hex_code' => '#FFA500'],
            ['type' => 'color', 'value' => 'Pink', 'hex_code' => '#FFC0CB'],
            ['type' => 'color', 'value' => 'Brown', 'hex_code' => '#A52A2A'],
            ['type' => 'color', 'value' => 'Grey', 'hex_code' => '#808080'],
            ['type' => 'color', 'value' => 'Beige', 'hex_code' => '#F5F5DC'],
            ['type' => 'color', 'value' => 'Gold', 'hex_code' => '#FFD700'],
            ['type' => 'color', 'value' => 'Silver', 'hex_code' => '#C0C0C0'],
            ['type' => 'size', 'value' => 'XS'],
            ['type' => 'size', 'value' => 'S'],
            ['type' => 'size', 'value' => 'M'],
            ['type' => 'size', 'value' => 'L'],
            ['type' => 'size', 'value' => 'XL'],
            ['type' => 'size', 'value' => 'XXL'],
            ['type' => 'size', 'value' => 'One Size'],
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

