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
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
//import \Log;
use Illuminate\Support\Facades\Log;
use App\Services\DeliveryService;
use Exception;



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

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'media' => 'required|file',
            'mediaType' => 'nullable|string|in:picture,gif,video',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
        ]);

        $mediaUrl = $this->generalService->uploadMedia(
            $validated['media'],
            $validated['mediaType'] ?? 'picture',
            $validated['width'] ?? null,
            $validated['height'] ?? null
        );

        return response()->json([
            'message' => 'Media uploaded successfully.',
            'media_url' => $mediaUrl,
        ]);
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
            'weight' => 'nullable|numeric',
            'images' => 'array',
            'images.*.file' => 'required|image|max:2048',
            'images.*.color_id' => 'nullable|exists:attributes,id',
            'discount' => 'nullable|numeric',
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
        // foreach ($request->images as $image) {
        //     // Get the original file extension
        //     $fileExtension = $image['file']->getClientOriginalExtension();

        //     // Generate a unique, code-based file name
        //     $fileName = uniqid('img_') . '_' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT) . '.' . $fileExtension;

        //     // Save the file in the desired directory
        //     $path = public_path('storage/products');
        //     $image['file']->move($path, $fileName);

        //     // Generate the full URL to the image
        //     $imageUrl = url('storage/products/' . $fileName);

        //     // Store the URL in the database
        //     $product->images()->create([
        //         'image_path' => $imageUrl,
        //         'color_id' => $image['color_id']
        //     ]);

        // }


        if ($request->has('images')) {
            foreach ($request->images as $image) {
                $path = $image['file']->getRealPath();
                $imageUrl = $this->generalService->uploadMedia($path, 'Product');
                $product->images()->create(['image_path' => $imageUrl, 'color_id' => $image['color_id']]);
            }
        }

        return response()->json(['message' => 'Product created successfully.', 'product' => $product->load('images')], 201);
    }


    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'brand', 'images.color', 'attributes']));
    }


    protected function getDeliveryCost($deliveryDetails)
    {
        try {
            $fezService = app(DeliveryService::class);
            return $fezService->calculateDeliveryCost([
                'pickUpState' => $deliveryDetails['pickUpState'] ?? "Lagos",
                'state' => $deliveryDetails['state'],
                'weight' => $deliveryDetails['weight'] ?? 1,
            ]);
        } catch (Exception $e) {
            // \Log::error('Delivery cost calculation failed: ' . $e->getMessage());
            return null;
        }
    }

    public function confirmPrice(Request $request)
    {
        $products = $request->input('products', []);
        $returnCurrency = $request->input('returnCurrency', 'USD');
        $deliveryDetails = $request->input('delivery_details');
        $results = [];
        $totalValue = 0;
        //get sum of weight of all the products
        $totalWeight = 0;

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

            if (!empty($product->discount) && $product->discount > 0) {
                $price = $product->price - ($product->price * ($product->discount / 100));
            }

            $totalPrice = $price * $quantity;
            $convertedPrice = $this->generalService->convertMoney($baseCurrency, $totalPrice, $returnCurrency);
            $totalValue += $convertedPrice;
            $totalWeight += $product->weight * $quantity;

            $results[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'amount' => $convertedPrice,
                'currency' => $returnCurrency,
                'weight' => $product->weight * $quantity, // Assuming weight is in kg
            ];
        }

        // Calculate delivery cost if delivery details are provided
        $deliveryCost = 0;
        $deliveryDetails['weight'] = $totalWeight;
        if (!empty($deliveryDetails)) {
            $deliveryCostResponse = $this->getDeliveryCost($deliveryDetails);
            if ($deliveryCostResponse && isset($deliveryCostResponse['Cost'])) {
                $deliveryCost = $this->generalService->convertMoney(
                    'NGN',
                    $deliveryCostResponse['Cost']['cost'],
                    $returnCurrency
                );
            }
        }

        $data = [
            'products' => $results,
            'subtotal' => number_format($totalValue, 2),
            'delivery_cost' => number_format($deliveryCost, 2),
            'total_price' => number_format($totalValue + $deliveryCost, 2),
            'currency' => $returnCurrency,
            'total_weight' => number_format($totalWeight, 2),
        ];

        return $this->success('Prices converted successfully', $data, [], 200);
    }



    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'string',
            'category_id' => 'exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'price' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'images' => 'nullable|array',
            'images.*.file' => 'nullable|image|max:2048',
            'images.*.color_id' => 'nullable|exists:attributes,id',
            'attributes' => 'array',
        ]);

        $product = Product::findOrFail($id);

        $product->update($validated);

        // Update attributes
        if ($request->has('attributes')){
            $product->attributes()->sync($request->attributes ?? []);
        }

        if ($request->has('images')) {
            foreach ($request->images as $image) {
                $path = $image['file']->getRealPath();
                $imageUrl = $this->generalService->uploadMedia($path, 'Product');
                $product->images()->create(['image_path' => $imageUrl, 'color_id' => $image['color_id']]);
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



    public function getAllProducts(Request $request)
    {
        // $validatedData = $request->validate([
        //     'lower_budget' => ['sometimes', 'required_with:upper_budget', 'numeric'],
        //     'upper_budget' => ['sometimes', 'required_with:lower_budget', 'numeric']
        // ]);

        // $validatedData = $request->validate([
        //     'lower_budget' => 'sometimes|numeric|upper_budget>=lower_budget',
        //     'upper_budget' => 'sometimes|numeric|lower_budget<=upper_budget'
        // ]);

        // Start the query with eager loading
        $query = Product::with(['category', 'brand', 'images.color', 'attributes']);
        $returnCurrency = $request->returnCurrency;

        // Apply filters based on the request parameters
        if ($request->has('search') && $request->search) {
            $query->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search}%")
                      ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        if ($request->has('brand_id') && $request->brand_id) {
            $query->whereHas('brand', function ($query) use ($request) {
                $query->where('brands.id', $request->brand_id);
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

        // $getMaxMinPrice = Product::selectRaw('MIN(price) AS min_price, MAX(price) AS max_price')->first();
        if ($request->has('lower_budget') && $request->lower_budget && $request->has('upper_budget') && $request->upper_budget) {
            $lowerBudgetUSD = $this->generalService->convertMoney($returnCurrency, $request->lower_budget, 'USD');
            $upperBudgetUSD = $this->generalService->convertMoney($returnCurrency, $request->upper_budget, 'USD');
            $query->whereBetween('price', [$lowerBudgetUSD, $upperBudgetUSD]);
        } elseif ($request->has('lower_budget') && $request->lower_budget) {
            $lowerBudgetUSD = $this->generalService->convertMoney($returnCurrency, $request->lower_budget, 'USD');
            $query->where('price', '>=', $lowerBudgetUSD);
        } elseif ($request->has('upper_budget') && $request->upper_budget) {
            $upperBudgetUSD = $this->generalService->convertMoney($returnCurrency, $request->upper_budget, 'USD');
            $query->where('price', '<=', $upperBudgetUSD);
        }

        // Apply pagination: get page and limit from the request or set defaults
        $perPage = $request->has('perPage') ? (int)$request->perPage : 30; // Default 15 products per page
        $page = $request->has('page') ? (int)$request->page : 1; // Default to the first page

        // Paginate the query results
        $products = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        // $products = $query->get();

        // If currency conversion is needed, apply the conversion
        if ($request->has('returnCurrency') && $request->returnCurrency) {
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

        $perPage = $request->has('perPage') ? (int)$request->perPage : 30;
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






    public function destroy($id){
        $product = Product::find($id);
        $images = ProductImage::where('product_id', $id)->get();
        foreach ($images as $image) {
            $publicId = $this->generalService->extractPublicId($image->image_path);
            Cloudinary::destroy($publicId);
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


    public function getProductDistribution()
    {
        if (request()->has('type') && request()->type === 'category') {
            $productData = Brand::withCount('products')->get();
        } elseif (request()->has('type') && request()->type === 'brand') {
            $productData = Brand::withCount('products')->get();
        } else {
            return response()->json(['message' => 'Invalid distribution type provided'], 400);
        }

        $data = $productData->map(function ($product) {
            return [
                'name' => $product->name,
                'count' => $product->products_count,
                'percentage' => number_format($product->products_count / Product::count() * 100, 2, '.', ''),
            ];
        });

        return $this->success('Product data fetched successfully', $data, [], 200);
    }

}

