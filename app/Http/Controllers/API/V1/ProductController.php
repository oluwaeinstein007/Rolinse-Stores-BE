<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    //
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'gender' => 'required|in:male,female',
            'category' => 'required|string',
            // 'color' => 'required|string',
            // 'stock' => 'required|integer',
            'colors' => 'required|array',
            'colors.*' => 'string',
            'stock' => 'required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',  // Validate image
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $request->image = $imagePath;
        }

        $product = Product::create($request->all());

        return response()->json($product, 201);
    }


     // Update the specified product in storage
     public function update(Request $request, Product $product)
     {
         $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'gender' => 'sometimes|in:male,female',
            'category' => 'sometimes|string',
            'color' => 'sometimes|string',
            'stock' => 'sometimes|integer',
         ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $imagePath = $request->file('image')->store('products', 'public');
            $product->image = $imagePath;
        }

         $product->update($request->all());

         return response()->json($product);
     }

     // Remove the specified product from storage
     public function destroy(Product $product)
     {
         $product->delete();

         return response()->json(['message' => 'Product deleted successfully']);
     }
}
