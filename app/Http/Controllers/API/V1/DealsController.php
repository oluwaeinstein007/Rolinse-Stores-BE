<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SpecialDeals;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Services\GeneralService;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class DealsController extends Controller
{
    //
    protected $generalService;
    protected $notificationService;

    public function __construct(GeneralService $generalService)
    {
        $this->generalService = $generalService;
        // $this->middleware('auth');
    }


    //get deal_type from special_deals
    public function getDealTypes($visibility = null)
    {
        //check if  visiblity

        $dealTypes = SpecialDeals::select('id','deal_type', 'slug', 'image')->get();

        return $this->success('Deal types fetched successfully', $dealTypes, [], 200);
    }


    //create or edit special deals
    public function createOrUpdateDealType(Request $request, $id = null)
    {
        $request->validate([
            'deal_type' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'visibility' => 'nullable|string',
        ]);

        $dealType = $request->deal_type;
        $imageUrl = null;

        if ($request->has('image')) {
            $path = $request->image->getRealPath();
            $imageUrl = $this->generalService->uploadMedia($path, 'Deals');
        }


        if ($id) {
            // Edit existing record if slug is provided
            $specialDeal = SpecialDeals::where('id', $id)->first();

            if (!$specialDeal) {
                return response()->json(['error' => 'Special deal not found.'], 404);
            }

            $specialDeal->update([
                'deal_type' => $dealType,
                'slug' => Str::slug($dealType, '_'),
                'image' => $imageUrl ?? $specialDeal->image, // Keep old image if no new one is provided
                'visibility' => $request->visibility ?? $specialDeal->visibility
            ]);

            return response()->json(['message' => 'Special deal updated successfully.', 'deal' => $specialDeal]);
        } else {
            // Create or update if slug is not provided
            $slug = Str::slug($dealType, '_');
            $specialDeal = SpecialDeals::updateOrCreate(
                ['deal_type' => $dealType],
                [
                    'deal_type' => $dealType,
                    'slug' => $slug,
                    'image' => $imageUrl,
                    'visibility' => $request->visibility
                ]
            );

            return response()->json(['message' => 'Special deal created successfully.', 'deal' => $specialDeal]);
        }
    }


    // public function createOrUpdateDealType(Request $request, $id = null)
    // {
    //     $request->validate([
    //         'deal_type' => 'required|string',
    //         'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    //         'visibility' => 'nullable|string',
    //     ]);

    //     $imageUrl = null;

    //     if ($request->hasFile('image')) {
    //         $path = $request->file('image')->getRealPath();
    //         $imageUrl = $this->generalService->uploadMedia($path, 'Deals');
    //     }

    //     $data = [
    //         'deal_type' => $request->deal_type,
    //         'slug' => Str::slug($request->deal_type, '_'),
    //         'image' => $imageUrl,
    //         'visibility' => $request->visibility,
    //     ];

    //     if ($id) {
    //         $specialDeal = SpecialDeals::find($id);

    //         if (!$specialDeal) {
    //             return response()->json(['error' => 'Special deal not found.'], 404);
    //         }

    //         $specialDeal->update(array_filter($data)); // `array_filter` removes null values, keeping existing data
    //         return response()->json(['message' => 'Special deal updated successfully.', 'deal' => $specialDeal]);
    //     }

    //     $specialDeal = SpecialDeals::updateOrCreate(
    //         ['deal_type' => $request->deal_type],
    //         $data
    //     );

    //     return response()->json(['message' => 'Special deal created successfully.', 'deal' => $specialDeal]);
    // }



    public function deleteDealTypes($id)
    {
        // Find the special deal by the deal type
        $specialDeal = SpecialDeals::where('id', $id)->first();

        if (!$specialDeal) {
            return response()->json(['message' => 'Deal type not found.'], 404);
        }

        // Delete associated image file if it exists
        if ($specialDeal->image) {
            $publicId = $this->generalService->extractPublicId($specialDeal->image);
            Cloudinary::destroy($publicId);
        }

        // Delete the deal type
        $specialDeal->delete();

        return response()->json(['message' => 'Deal type deleted successfully.'], 200);
    }



    //get all special deals, if deal_type is specified, get all products with that deal_type
    public function getSpecialDeals($dealType = null)
    {
        $query = Product::with(['category', 'brand', 'images.color', 'attributes'])
            ->whereNotNull('special_deal_slug');

        if ($dealType) {
            $query->where('special_deal_slug', $dealType);
        }

        $products = $query->get();
        $counts = $products->count();
        $data = [
            'counts' => $counts,
            'products' => $products,
        ];

        return $this->success('Offer Products fetched successfully.', $data, [], 201);
    }


    public function addProductDeal(Request $request)
    {
        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.discount' => 'required|numeric|min:0|max:100',
            'deal_slug' => 'required|exists:special_deals,slug',
        ]);

        $deals = [];

        $dealType = SpecialDeals::where('slug', $validated['deal_slug'])->first();


        foreach ($validated['products'] as $dealData) {
            // Fetch product and update deal fields
            $product = Product::find($dealData['product_id']);

            $product->special_deal_slug = $dealType->slug;
            $product->discount = $dealData['discount'];
            $product->save();

            $deals[] = $product;
        }

        return $this->success('Deals created successfully.', $deals, [], 201);
    }


    public function clearProductDeal(Request $request)
    {
        $validated = $request->validate([
            'deal_type' => 'nullable|exists:special_deals,slug|required_without:product_ids',
            'product_ids' => 'nullable|array|min:1|required_without:deal_type',
            'product_ids.*' => 'required|exists:products,id',
        ]);

        // Ensure at least one of deal_type or product_ids is provided
        if (!$request->has('deal_type') && (empty($validated['product_ids']) || !$request->has('product_ids'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Either deal_type or product_ids must be provided.',
            ], 422);
        }

        // Handle clearing products based on deal_type
        if ($request->has('deal_type')) {
            Product::where('special_deal_slug', $validated['deal_type'])
                ->update([
                    'special_deal_slug' => null,
                    'discount' => 0,
                ]);
        }

        // Handle clearing specific products based on product_ids
        if (!empty($validated['product_ids'])) {
            Product::whereIn('id', $validated['product_ids'])
                ->update([
                    'special_deal_slug' => null,
                    'discount' => 0,
                ]);
        }

        return response()->json([
            'message' => 'Deals deleted successfully.',
        ], 200);
    }


    //get all images in Category, Brand, Product, SpecialDeals, create on cloudinary, then return a json list of all images, with their public_id in array ie $brand images, $category images, $product images, $special_deals images
    // public function handleImages(){
    //     $brands = asset('storage/Brands');
    //     $categories = asset('storage/Categories');
    //     $products = asset('storage/products');



    //     $images = [];

    //     //list the images in each of those folders ie Brands, Categories, Products separately
    //     $brandImages = Storage::files('public/storage/Brands');
    //     $categoryImages = Storage::files('public/Categories');
    //     $productImages = Storage::files('public/products');

    //     return $brandImages;

    //     //create the images on cloudinary
    //     foreach ($brandImages as $brandImage) {
    //         $publicId = $this->generalService->extractPublicId($brandImage);
    //         $image = Cloudinary::upload($brandImage, ['public_id' => $publicId]);
    //         $images['brands'][] = $image;
    //     }

    //     foreach ($categoryImages as $categoryImage) {
    //         $publicId = $this->generalService->extractPublicId($categoryImage);
    //         $image = Cloudinary::upload($categoryImage, ['public_id' => $publicId]);
    //         $images['categories'][] = $image;
    //     }

    //     foreach ($productImages as $productImage) {
    //         $publicId = $this->generalService->extractPublicId($productImage);
    //         $image = Cloudinary::upload($productImage, ['public_id' => $publicId]);
    //         $images['products'][] = $image;
    //     }


    //     return response()->json($images);
    // }
    public function handleImages(){
        // Define the paths for the images
        $brandsPath = 'Brands';
        $categoriesPath = 'Categories';
        $productsPath = 'products';

        // Initialize the images array
        $images = [];

        // List the images in each of those folders (Brands, Categories, Products)
        $brandImages = Storage::files("public/{$brandsPath}");
        $categoryImages = Storage::files("public/{$categoriesPath}");
        $productImages = Storage::files("public/{$productsPath}");

        // Upload brand images to Cloudinary
        foreach ($brandImages as $brandImage) {
            // Get the full path to the file
            $fullPath = storage_path('app/' . $brandImage);

            // Upload the image to Cloudinary using the generalService
            $imageUrl = $this->generalService->uploadMedia($fullPath, 'Brand');

            // Add the Cloudinary URL to the images array
            $images['brands'][] = $imageUrl;
        }

        // Upload category images to Cloudinary
        foreach ($categoryImages as $categoryImage) {
            // Get the full path to the file
            $fullPath = storage_path('app/' . $categoryImage);

            // Upload the image to Cloudinary using the generalService
            $imageUrl = $this->generalService->uploadMedia($fullPath, 'Category');

            // Add the Cloudinary URL to the images array
            $images['categories'][] = $imageUrl;
        }

        // Upload product images to Cloudinary
        foreach ($productImages as $productImage) {
            // Get the full path to the file
            $fullPath = storage_path('app/' . $productImage);

            // Upload the image to Cloudinary using the generalService
            $imageUrl = $this->generalService->uploadMedia($fullPath, 'Product');

            // Add the Cloudinary URL to the images array
            $images['products'][] = $imageUrl;
        }

        // Return the images array
        return $images;
    }


}
