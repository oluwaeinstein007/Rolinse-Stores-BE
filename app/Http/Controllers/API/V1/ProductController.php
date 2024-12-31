<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\BestSeller;
use Carbon\Carbon;
use App\Models\ProductImage;
use App\Models\SpecialDeals;
use Illuminate\Support\Facades\Storage;
use App\Services\GeneralService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

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
        $brands = Brand::select('id', 'name', 'slug', 'image')->get();
        $categories = Category::select('id', 'name', 'slug', 'image')->get();
        $sizes = Attribute::where('type', 'size')->select('id','type','value')->get();
        $colors = Attribute::where('type', 'color')->select('id','type','value', 'hex_code')->get();

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


    public function getProduct(Request $request)
    {
        $product = Product::with([
            'category',
            'brand',
            'images.color',
            'attributes'
        ])->findOrFail($request->id);

        // Define the base currency and return currency
        $baseCurrency = $product->baseCurrency ?: 'USD';
        $price = $product->price;
        $returnCurrency = $request->input('returnCurrency', 'USD');
        // Convert the price to the requested currency
        $product->price = $this->generalService->convertMoney($baseCurrency, $price, $returnCurrency);

        $relatedProducts = Product::with(['images', 'brand'])
            ->where('category_id', $product->category_id)
            ->where('brand_id', $product->brand_id)
            ->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        // Convert prices of related products
        $relatedProducts->each(function ($relatedProduct) use ($returnCurrency) {
            $relatedProduct->price = $this->generalService->convertMoney(
                $relatedProduct->baseCurrency ?? 'USD',
                $relatedProduct->price,
                $returnCurrency
            );
        });

        $data = [
            'product' => $product,
            'related_products' => $relatedProducts,
        ];

        return $this->success('Products fetched successfully', $data, [], 200);
    }


    // public function getAllProducts(Request $request){
    //     $query = Product::with(['category', 'brand', 'images.color', 'attributes']);

    //     // Apply filters based on the request parameters
    //     if ($request->has('brand_id') && $request->brand_id) {
    //         $query->whereHas('brand', function ($query) use ($request) {
    //             $query->where('brands.id', $request->brand_id);  // specify table name 'brands'
    //         });
    //     }

    //     if ($request->has('category_id') && $request->category_id) {
    //         $query->whereHas('category', function ($query) use ($request) {
    //             $query->where('categories.id', $request->category_id);  // specify table name 'categories'
    //         });
    //     }

    //     if ($request->has('size_id') && $request->size_id) {
    //         $query->whereHas('attributes', function ($query) use ($request) {
    //             $query->where('attributes.type', 'size')
    //                 ->where('attributes.id', $request->size_id);  // specify table name 'attributes'
    //         });
    //     }

    //     if ($request->has('color_id') && $request->color_id) {
    //         $query->whereHas('images.color', function ($query) use ($request) {
    //             $query->where('attributes.id', $request->color_id);  // specify table name 'attributes'
    //         });
    //     }

    //     $products = $query->get();

    //     if ($request->has('returnCurrency') && $request->returnCurrency) {
    //         $returnCurrency = $request->returnCurrency;
    //         foreach ($products as $product) {
    //             $baseCurrency = $product->baseCurrency ?: 'USD';
    //             $product->price = $this->generalService->convertMoney($baseCurrency, $product->price, $returnCurrency);
    //         }
    //     }

    //     // // Apply budget filters
    //     // if ($request->has('upper_budget') && $request->upper_budget && $request->has('lower_budget') && $request->lower_budget) {
    //     //     $products = $products->filter(function ($product) use ($request) {
    //     //         return $product->converted_price <= $request->upper_budget && $product->converted_price >= $request->lower_budget;
    //     //     });
    //     // } elseif ($request->has('upper_budget') && $request->upper_budget) {
    //     //     // Apply upper budget filter only
    //     //     $products = $products->filter(function ($product) use ($request) {
    //     //         return $product->converted_price <= $request->upper_budget;
    //     //     });
    //     // } elseif ($request->has('lower_budget') && $request->lower_budget) {
    //     //     // Apply lower budget filter only
    //     //     $products = $products->filter(function ($product) use ($request) {
    //     //         return $product->converted_price >= $request->lower_budget;
    //     //     });
    //     // }

    //     return response()->json($products);
    // }

    public function getAllProducts(Request $request)
    {
        // Start the query with eager loading
        $query = Product::with(['category', 'brand', 'images.color', 'attributes']);

        // Apply filters based on the request parameters
        if ($request->has('brand_id') && $request->brand_id) {
            $query->whereHas('brand', function ($query) use ($request) {
                $query->where('brands.id', $request->brand_id);  // specify table name 'brands'
            });
        }

        if ($request->has('category_id') && $request->category_id) {
            $query->whereHas('category', function ($query) use ($request) {
                $query->where('categories.id', $request->category_id);  // specify table name 'categories'
            });
        }

        if ($request->has('size_id') && $request->size_id) {
            $query->whereHas('attributes', function ($query) use ($request) {
                $query->where('attributes.type', 'size')
                    ->where('attributes.id', $request->size_id);  // specify table name 'attributes'
            });
        }

        if ($request->has('color_id') && $request->color_id) {
            $query->whereHas('images.color', function ($query) use ($request) {
                $query->where('attributes.id', $request->color_id);  // specify table name 'attributes'
            });
        }

        // Apply pagination: get page and limit from the request or set defaults
        $perPage = $request->has('perPage') ? (int)$request->perPage : 15; // Default 15 products per page
        $page = $request->has('page') ? (int)$request->page : 1; // Default to the first page

        // Paginate the query results
        $products = $query->paginate($perPage, ['*'], 'page', $page);

        // If currency conversion is needed, apply the conversion
        if ($request->has('returnCurrency') && $request->returnCurrency) {
            $returnCurrency = $request->returnCurrency;
            foreach ($products as $product) {
                $baseCurrency = $product->baseCurrency ?: 'USD';
                $product->price = $this->generalService->convertMoney($baseCurrency, $product->price, $returnCurrency);
            }
        }

        // Return paginated results as JSON
        return $this->success('Products fetched successfully', $products, [], 200);
    }


    //get product by category ie filter by category slug or gender ie if men get all item related to men
    public function getProductByCategory(Request $request)
    {
        $categoryMappings = [
            'men' => ['mens-clothing', 'mens-footwear', 'mens-accessories'],
            'women' => ['womens-clothing', 'womens-footwear', 'womens-accessories'],
            'kids' => ['kids-clothing', 'kids-footwear', 'kids-accessories'],
            'accessories' => ['mens-accessories', 'mens-accessories', 'mens-accessories'],
            // 'mens-accessories' => ['mens-accessories']
        ];

        // Check if the category is valid
        if (!array_key_exists($request->category, $categoryMappings)) {
            return response()->json(['message' => 'Invalid category provided'], 400);
        }

        // Get category slugs associated with the provided category
        $categorySlugs = $categoryMappings[$request->category];

        // Fetch the categories by slug
        $categories = Category::whereIn('slug', $categorySlugs)->get();

        if ($categories->isEmpty()) {
            return response()->json(['message' => 'Categories not found'], 404);
        }

        $perPage = $request->has('perPage') ? (int)$request->perPage : 15;
        $page = $request->has('page') ? (int)$request->page : 1;

        // Fetch products related to the categories
        $products = Product::whereIn('category_id', $categories->pluck('id'))
                    ->with(['category', 'brand', 'images.color', 'attributes'])
                    ->paginate($perPage, ['*'], 'page', $page);

        // If currency conversion is needed, apply the conversion
        if ($request->has('returnCurrency') && $request->returnCurrency) {
            $returnCurrency = $request->returnCurrency;
            foreach ($products as $product) {
                $baseCurrency = $product->baseCurrency ?: 'USD';
                $product->price = $this->generalService->convertMoney($baseCurrency, $product->price, $returnCurrency);
            }
        }

        return $this->success('Products fetched successfully', $products, [], 200);
    }






    public function destroy(Product $product)
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }


    public function bestSeller(Request $request)
    {
        // Check if the oldest record in the BestSeller table is older than 1 month
        $oldestRecord = BestSeller::orderBy('created_at', 'asc')->first();

        if ($oldestRecord && $oldestRecord->created_at < Carbon::now()->subMonth() || !$oldestRecord) {
            // Clear the table and repopulate it
            BestSeller::truncate();

            // Fetch the top products by sales quantity from the orders
            $products = Product::select(
                'products.id as product_id',
                DB::raw('SUM(order_items.quantity) as orders_count')
            )
                ->join('order_items', 'products.id', '=', 'order_items.product_id')
                ->groupBy('products.id')
                ->orderBy('orders_count', 'desc')
                ->limit(10) // Limit to top 10 products
                ->get();

            // Update or create best-selling products in the BestSeller table
            foreach ($products as $product) {
                BestSeller::updateOrCreate(
                    ['product_id' => $product->product_id], // Unique key to match existing records
                    ['orders_count' => $product->orders_count] // Update or set the total orders count
                );
            }
        }

        // Fetch the best-selling products from the BestSeller table
        // $bestSellers = BestSeller::with('product')->get();
        $bestSellers = BestSeller::with([
            'product' => function ($query) {
                $query->with(['category', 'brand', 'images.color', 'attributes']);
            }
        ])->get();

        return $this->success('Products fetched successfully', $bestSellers, [], 200);
    }

}

