<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\AdminPromo;
use App\Models\ProductImage;
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

    // public function placeOrder(Request $request)
    // {
    //     $user = $request->user(); // Ensure the user is authenticated
    //     $products = $request->input('products', []);
    //     $returnCurrency = $request->input('returnCurrency', 'USD');

    //     if (empty($products)) {
    //         return $this->failure('No products provided', [], 400);
    //     }

    //     $orderItems = [];
    //     $totalPrice = 0;

    //     foreach ($products as $productData) {
    //         $productId = $productData['product_id'];
    //         $quantity = $productData['quantity'] ?? 1;

    //         $product = Product::find($productId);
    //         if (!$product) {
    //             return $this->failure("Product with ID {$productId} not found", [], 404);
    //         }

    //         $baseCurrency = $product->baseCurrency ?: 'USD';
    //         $price = $product->price;
    //         $totalPriceForProduct = $price * $quantity;

    //         $convertedPrice = $this->generalService->convertMoney($baseCurrency, $totalPriceForProduct, $returnCurrency);

    //         $orderItems[] = [
    //             'product_id' => $productId,
    //             'quantity' => $quantity,
    //             'price_per_unit' => $convertedPrice / $quantity,
    //             'total_price' => $convertedPrice,
    //             'currency' => $returnCurrency,
    //         ];

    //         $totalPrice += $convertedPrice;
    //     }

    //     // Create the order
    //     $order = Order::create([
    //         'user_id' => $user->id,
    //         'order_number' => uniqid('ORD-'),
    //         'status' => 'pending', // Default order status
    //         'grand_total' => $totalPrice,
    //         'item_count' => count($orderItems),
    //     ]);

    //     // Attach items to the order
    //     foreach ($orderItems as $item) {
    //         OrderItem::create(array_merge($item, ['order_id' => $order->id]));
    //     }

    //     return $this->success('Order placed successfully', [
    //         'order_id' => $order->id,
    //         'order_number' => $order->order_number,
    //         'grand_total' => number_format($totalPrice, 2),
    //         'currency' => $returnCurrency,
    //         'items' => $orderItems,
    //     ], [], 201);
    // }

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

        return response()->json($response, 201);
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





}
