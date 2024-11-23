<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Attribute;
use Illuminate\Support\Facades\Storage;
use App\Services\GeneralService;
use App\Services\NotificationService;

// class ProductController extends Controller
// {
//     //
//     public function store(Request $request)
//     {
//         $request->validate([
//             'name' => 'required|string|max:255',
//             'description' => 'required|string',
//             'price' => 'required|numeric',
//             'gender' => 'required|in:male,female',
//             'category' => 'required|string',
//             // 'color' => 'required|string',
//             // 'stock' => 'required|integer',
//             'colors' => 'required|array',
//             'colors.*' => 'string',
//             'stock' => 'required|integer',
//             'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',  // Validate image
//         ]);

//         $imagePath = null;
//         if ($request->hasFile('image')) {
//             $imagePath = $request->file('image')->store('products', 'public');
//             $request->image = $imagePath;
//         }

//         $product = Product::create($request->all());

//         return response()->json($product, 201);
//     }


//      // Update the specified product in storage
//      public function update(Request $request, Product $product)
//      {
//          $request->validate([
//             'name' => 'sometimes|string|max:255',
//             'description' => 'sometimes|string',
//             'price' => 'sometimes|numeric',
//             'gender' => 'sometimes|in:male,female',
//             'category' => 'sometimes|string',
//             'color' => 'sometimes|string',
//             'stock' => 'sometimes|integer',
//          ]);

//         // Handle image upload
//         if ($request->hasFile('image')) {
//             // Delete old image if exists
//             if ($product->image) {
//                 Storage::disk('public')->delete($product->image);
//             }
//             $imagePath = $request->file('image')->store('products', 'public');
//             $product->image = $imagePath;
//         }

//          $product->update($request->all());

//          return response()->json($product);
//      }

//      // Remove the specified product from storage
//      public function destroy(Product $product)
//      {
//          $product->delete();

//          return response()->json(['message' => 'Product deleted successfully']);
//      }
// }


class ProductController extends Controller
{
    protected $generalService;
    protected $notificationService;

    public function __construct(GeneralService $generalService, NotificationService $notificationService)
    {
        $this->generalService = $generalService;
        $this->notificationService = $notificationService;
        // $this->middleware('auth');
    }


    public function index(Request $request)
    {
        $products = Product::with(['category', 'brand', 'images.color', 'attributes'])
            ->when($request->category, fn($query) => $query->whereHas('category', fn($q) => $q->where('name', $request->category)))
            ->when($request->brand, fn($query) => $query->whereHas('brand', fn($q) => $q->where('name', $request->brand)))
            ->paginate(10);

        return response()->json($products);
    }

    // public function search(Request $request)
    // {
    //     $products = Product::with(['category', 'brand', 'images.color', 'attributes'])
    //         ->where('name', 'like', "%{$request->search}%")
    //         ->orWhere('description', 'like', "%{$request->search}%")
    //         ->paginate(10);

    //     return response()->json($products);
    // }
    public function getTypes()
    {
        $brands = Brand::select('id', 'name')->get();
        $categories = Category::select('id', 'name')->get();
        $sizes = Attribute::where('type', 'size')->select('id','type','value')->get();
        $colors = Attribute::where('type', 'color')->select('id','type','value')->get();

        $data = [
            'brands' => $brands,
            'categories' => $categories,
            'sizes' => $sizes,
            'colors' => $colors,
        ];

        return $this->success('Types and Attributes retrieved successfully', $data, [], 200);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'material' => 'required|string',
            'price' => 'required|numeric',
            'images' => 'array',
            'images.*.file' => 'required|image|max:2048',
            'images.*.color_id' => 'nullable|exists:attributes,id',
            // 'attributes' => 'array',
        ]);

        $product = Product::create($validated);

        // Attach attributes
        if ($request->has('attributes')) {
            $attributes = explode(',', trim($request->get('attributes'), '[]'));
            $attributes = array_filter(array_map('intval', $attributes));
            $request->merge(['attributes' => $attributes]);
            // return $request->get('attributes');
            foreach($attributes as $attribute) {
                $product->attributes()->attach($attribute);
            }
        }

        // Save images
        foreach ($request->images as $image) {
            $path = $image['file']->store('products', 'public');
            $product->images()->create(['image_path' => $path, 'color_id' => $image['color_id']]);
        }

        return response()->json(['message' => 'Product created successfully.', 'product' => $product->load('images')], 201);
    }


    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'brand', 'images.color', 'attributes']));
    }


    // public function confirmPrice(Request $request)
    // {
    //     $productId = $request->product_id ?: 1;
    //     $product = Product::find($productId);
    //     $price = $product->price;
    //     $baseCurrency = $product->baseCurrency ?:'USD';
    //     $returnCurrency = $request->returnCurrency ?:'USD';

    //     $convertedPrice = $this->generalService->convertMoney($baseCurrency, $price, $returnCurrency);

    //     return $this->success('Price converted successfully', ['price' => $convertedPrice], [], 200);
    // }


    public function confirmPrice(Request $request)
    {
        $products = $request->input('products', []);
        $returnCurrency = $request->input('returnCurrency', 'USD');
        $results = [];

        foreach ($products as $productData) {
            $productId = $productData['product_id'];
            $quantity = $productData['quantity'] ?? 1;

            $product = Product::find($productId);
            if (!$product) {
                $results[] = [
                    'product_id' => $productId,
                    'error' => 'Product not found'
                ];
                continue;
            }

            $baseCurrency = $product->baseCurrency ?: 'USD';
            $price = $product->price;
            $totalPrice = $price * $quantity;

            $convertedPrice = $this->generalService->convertMoney($baseCurrency, $totalPrice, $returnCurrency);

            $results[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'amount' => $convertedPrice,
                'currency' => $returnCurrency
            ];
        }

        $data = [
            // 'total' => array_sum(array_column($results, 'total_price')),
            'total_price' => number_format(array_sum(array_column($results, 'amount')), 2),
            'currency' => $returnCurrency,
            'products' => $results
        ];

        return $this->success('Prices converted successfully', $data, [], 200);
    }



    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'string',
            'category_id' => 'exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'material' => 'string',
            'price' => 'numeric',
            'images' => 'array',
            'images.*.file' => 'image|max:2048',
            'images.*.color_id' => 'nullable|exists:attributes,id',
            'attributes' => 'array',
        ]);

        $product->update($validated);

        // Update attributes
        if ($request->has('attributes')){
            $product->attributes()->sync($request->attributes ?? []);
        }

        // Update images
        if ($request->has('images')) {
            foreach ($request->images as $image) {
                $path = $image['file']->store('products', 'public');
                $product->images()->create(['image_path' => $path, 'color_id' => $image['color_id']]);
            }
        }

        return response()->json(['message' => 'Product updated successfully.', 'product' => $product->load('images')]);
    }


    public function destroy(Product $product)
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}

