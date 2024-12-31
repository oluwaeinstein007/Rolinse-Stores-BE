<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SpecialDeals;
use Illuminate\Support\Str;

class SpecialDealSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $deals = [
            ['name' => "Deal of the Month", 'image' => 'https://via.placeholder.com/150?text=Deal+of+the+month'],
            ['name' => "Christmas Sale", 'image' => 'https://via.placeholder.com/150?text=Christmas+Deal'],
            ['name' => "New Year Sale", 'image' => 'https://via.placeholder.com/150?text=New+Year+Deal'],
            ['name' => "Black Friday Deal", 'image' => 'https://via.placeholder.com/150?text=Black+Friday+Deal'],
            ['name' => "Summer Sale", 'image' => 'https://via.placeholder.com/150?text=Summer+Deal'],
            ['name' => "Winter Sale", 'image' => 'https://via.placeholder.com/150?text=Winter+Deal'],
            ['name' => "Spring Sale", 'image' => 'https://via.placeholder.com/150?text=Spring+Deal'],
            ['name' => "Autumn Sale", 'image' => 'https://via.placeholder.com/150?text=Autumn+Deal'],
            ['name' => "Easter Sale", 'image' => 'https://via.placeholder.com/150?text=Easter+Deal'],
            ['name' => "Valentine's Day Deal", 'image' => 'https://via.placeholder.com/150?text=Valentine\'s+Day+Deal'],
        ];

        foreach ($deals as $deal) {
            SpecialDeals::updateOrCreate(
                ['deal_type' => $deal['name']],
                [
                    'deal_type' => $deal['name'],
                    'slug' => Str::slug($deal['name'], '_'),
                    'image' => $deal['image'],
                ]
            );
        }
    }
}
