<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Attribute;
use Illuminate\Support\Str;

class ProductAttributeSeeder extends Seeder
{
    public function run()
    {
        $product = Product::first(); // Example: Get a sample product
        $attributes = Attribute::whereIn('id', [1, 2, 3, 4, 5, 6])->get(); // Example: Select attributes by ID

        if ($product && $attributes->isNotEmpty()) {
            // Sync attributes to the product
            $product->attributes()->sync($attributes->pluck('id'));
        }


        // $sourceDir = public_path('storage/raw_products');
        // $destinationDir = public_path('storage/products');


        // // Ensure the destination directory exists
        // if (!file_exists($destinationDir)) {
        //     mkdir($destinationDir, 0755, true);
        // }

        // // Get all files in the source directory
        // $files = array_diff(scandir($sourceDir), ['.', '..']);

        // foreach ($files as $file) {
        //     $sourcePath = $sourceDir . '/' . $file;

        //     // Skip directories
        //     if (is_dir($sourcePath)) {
        //         continue;
        //     }

        //     // Extract file extension and validate it
        //     $extension = pathinfo($file, PATHINFO_EXTENSION);
        //     $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'jfif']; // Add other valid extensions as needed
        //     if (!in_array(strtolower($extension), $validExtensions)) {
        //         continue;
        //     }

        //     // Generate a shorter name (slugified) while keeping the original extension
        //     $newName = Str::slug(pathinfo($file, PATHINFO_FILENAME), '_') . '.' . strtolower($extension);

        //     // Destination path
        //     $destinationPath = $destinationDir . '/' . $newName;

        //     // Copy the file to the new directory with the shortened name
        //     if (copy($sourcePath, $destinationPath)) {
        //         echo "Processed: {$file} -> {$newName}" . PHP_EOL;
        //     } else {
        //         echo "Failed to process: {$file}" . PHP_EOL;
        //     }
        // }
    }
}

