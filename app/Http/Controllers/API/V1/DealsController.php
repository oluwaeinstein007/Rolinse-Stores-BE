<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SpecialDeals;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class DealsController extends Controller
{
    //
    //get deal_type from special_deals
    public function getDealTypes($visibility)
    {
        //check if  visiblity

        $dealTypes = SpecialDeals::select('deal_type', 'slug', 'image')->get();

        return $this->success('Deal types fetched successfully', $dealTypes, [], 200);
    }


    //create or edit special deals
    public function createOrUpdateDealType(Request $request, $id = null)
    {
        $request->validate([
            'deal_type' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $dealType = $request->deal_type;
        $imagePath = null;

        // Handle file upload if image is provided
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('special_deals', 'public');
            $imagePath = Storage::url($imagePath);
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
                'image' => $imagePath ?? $specialDeal->image, // Keep old image if no new one is provided
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
                    'image' => $imagePath,
                ]
            );

            return response()->json(['message' => 'Special deal created successfully.', 'deal' => $specialDeal]);
        }
    }



    public function deleteDealTypes($id)
    {
        // Find the special deal by the deal type
        $specialDeal = SpecialDeals::where('id', $id)->first();

        if (!$specialDeal) {
            return response()->json(['message' => 'Deal type not found.'], 404);
        }

        // Delete associated image file if it exists
        if ($specialDeal->image) {
            $imagePath = str_replace(url('storage'), 'public', $specialDeal->image);
            if (Storage::exists($imagePath)) {
                Storage::delete($imagePath);
            }
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


}
