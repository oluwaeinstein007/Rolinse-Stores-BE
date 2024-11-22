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
            ['type' => 'size', 'value' => 'S'],
            ['type' => 'size', 'value' => 'M'],
            ['type' => 'size', 'value' => 'L']
        ];

        foreach ($attributes as $attribute) {
            Attribute::updateOrCreate(
                ['type' => $attribute['type'], 'value' => $attribute['value']],
                $attribute
            );
        }
    }
}

