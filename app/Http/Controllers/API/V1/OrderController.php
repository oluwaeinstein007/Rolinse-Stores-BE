<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\AdminPromo;
use App\Models\ProductImage;
use App\Services\ActivityLogger;
use App\Services\GeneralService;
use App\Services\NotificationService;

class OrderController extends Controller
{
    protected $generalService;
    protected $notificationService;

    public function __construct(GeneralService $generalService, NotificationService $notificationService)
    {
        $this->generalService = $generalService;
        $this->notificationService = $notificationService;
        // $this->middleware('auth');
    }
    //


    public function placeOrder(Request $request)
    {
        $user = $request->authUser;

        $products = $request->input('products', []);
        $returnCurrency = $request->input('returnCurrency', 'USD');

        // Step 1: Validate all product IDs
        $productIds = array_column($products, 'product_id');
        $availableProducts = Product::whereIn('id', $productIds)->get()->keyBy('id'); // Fetch available products by ID

        $missingProducts = array_diff($productIds, $availableProducts->keys()->toArray());

        $orderItems = [];
        $grandTotal = 0;

        foreach ($products as $productData) {
            $productId = $productData['product_id'];
            $quantity = $productData['quantity'] ?? 1;

            if (!isset($availableProducts[$productId])) {
                // Skip missing products
                continue;
            }

            $product = $availableProducts[$productId];
            $price = $product->price;
            $totalPrice = $price * $quantity;

            $convertedPrice = $this->generalService->convertMoney(
                $product->baseCurrency ?: 'USD',
                $totalPrice,
                $returnCurrency
            );

            $image = ProductImage::where('product_id', $productId)
                    ->where('color_id', $productData['color_id'] ?? null)
                    ->first()
                    ->image_path ?? ProductImage::where('product_id', $productId)
                    ->first()
                    ->image_path ?? null;


            $orderItems[] = [
                'product_id' => $productId,
                'image' => $image,
                'quantity' => $quantity,
                'price_per_unit' => $price,
                'total_price' => $convertedPrice,
                'currency' => $returnCurrency
            ];

            $grandTotal += $convertedPrice;
        }

        if (empty($orderItems)) {
            return response()->json([
                'error' => 'No valid products found to place an order.',
                'missing_products' => $missingProducts
            ], 400);
        }

        // Step 2: Create the order
        $order = Order::create([
            'user_email' => $user->email ?? $request->email,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => 'pending',
            'grand_total' => $grandTotal,
            'item_count' => count($orderItems)
        ]);

        // Step 3: Save order items
        foreach ($orderItems as $item) {
            $item['order_id'] = $order->id;
            OrderItem::create($item);
        }

        if($request->has('promo_code')){
            $promo = AdminPromo::where('promo_code', $request->promo_code)->first();
            if($promo){
                $promo->users()->attach($user->id);
                $promo->updateMaxUses($promo->id);
            }
        }

        // Step 4: Return success response with warnings
        $response = [
            'status' => 'success',
            'message' => 'Order placed successfully',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'grand_total' => number_format($grandTotal, 2),
                'currency' => $returnCurrency,
                'items' => $orderItems
            ]
        ];

        if (!empty($missingProducts)) {
            $response['warning'] = 'Some products were not found and were skipped.';
            $response['missing_products'] = $missingProducts;
        }

        $full_name = $user->first_name . ' ' . $user->last_name;
        $this->notificationService->userNotification($user, 'Order', 'Order Placed', 'Order Placed.', 'You have placed an order with ID: ' . $order->order_number, false);
        ActivityLogger::log('Order', 'Order Placed', 'The order with ID: ' . $order->order_number . ' has been placed by ' . $full_name, $user->id);

        // return response()->json($response, 201);
        return $this->success('Order placed successfully', $response, [], 201);
    }


    public function getOrderHistory(Request $request)
    {
        $user = $request->authUser;

        $orders = Order::with(['items.product'])
            ->where('user_email', $user->email ?? $request->email)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return $this->success('Order history retrieved successfully', $orders, [], 200);
    }


    //get category distribution pie chart
    public function getCategorySalesDistribution()
    {
        $orders = Order::with(['items.product.category'])
            ->where('status', 'completed')
            ->get();

        $categoryDistribution = $orders->map(function ($order) {
            return $order->items->map(function ($item) {
                return $item->product->category->name;
            });
        })->flatten()->countBy();

        return $this->success('Category distribution retrieved successfully', $categoryDistribution, [], 200);
    }


    //get brand distribution pie chart
    public function getBrandSalesDistribution()
    {
        $orders = Order::with(['items.product.brand'])
            ->where('status', 'completed')
            ->get();

        $brandDistribution = $orders->map(function ($order) {
            return $order->items->map(function ($item) {
                return $item->product->brand->name;
            });
        })->flatten()->countBy();

        return $this->success('Brand distribution retrieved successfully', $brandDistribution, [], 200);
    }


    public function getOrderDistribution(){
        if (request()->has('type') && request()->type === 'category') {
            $orders =Order::with(['items.product.brand'])
            ->where('status', 'completed')
            ->get();
        } elseif (request()->has('type') && request()->type === 'brand') {
            $orders =Order::with(['items.product.brand'])
            ->where('status', 'completed')
            ->get();
        } else {
            return response()->json(['message' => 'Invalid distribution type provided'], 400);
        }

        $data = $orders->map(function ($order) {
            return $order->items->map(function ($item) {
                return $item->product->brand->name;
            });
        })->flatten()->countBy();

        return $this->success('Distribution data retrieved successfully', $data, [], 200);
    }


}
