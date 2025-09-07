<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\AdminPromo;
use App\Models\ProductImage;
use App\Models\Attribute;
use App\Services\ActivityLogger;
use App\Services\DeliveryService;
use App\Services\GeneralService;
use App\Services\NotificationService;
use App\Models\Delivery;
use Exception;

class OrderController extends Controller
{
    protected $generalService;
    protected $notificationService;
    protected $deliveryService;

    public function __construct(GeneralService $generalService, NotificationService $notificationService, DeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
        $this->generalService = $generalService;
        $this->notificationService = $notificationService;
        // $this->middleware('auth');
    }
    //


    public function placeOrder(Request $request)
    {
        $user = $request->authUser;
        $user = [
            'email' => $user->email ?? $request->delivery_details['recipientEmail'],
            'full_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $request->delivery_details['recipientName'],
            'id' => $user->id ?? null,
        ];
        $orderNumber = 'ORD-' . strtoupper(uniqid());

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

            $convertedTotalPrice = $this->generalService->convertMoney(
                $product->baseCurrency ?: 'USD',
                $totalPrice,
                $returnCurrency
            );

            $convertedPrice = $this->generalService->convertMoney(
                $product->baseCurrency ?: 'USD',
                $price,
                $returnCurrency
            );

            $image = ProductImage::where('product_id', $productId)
                    ->where('color_id', $productData['color_id'] ?? null)
                    ->first()
                    ->image_path ?? ProductImage::where('product_id', $productId)
                    ->first()
                    ->image_path ?? null;

            $color = Attribute::where('id',$productData['color_id'])->first()->value ?? null;


            $orderItems[] = [
                'product_id' => $productId,
                'image' => $image,
                'color' => $color,
                'quantity' => $quantity,
                'price_per_unit' => $convertedPrice,
                'total_price' => $convertedTotalPrice,
                'currency' => $returnCurrency
            ];

            $grandTotal += $convertedTotalPrice;
            //grand total in ngn
            $grandTotalNGN = $this->generalService->convertMoney($returnCurrency, $grandTotal, 'NGN');
        }

        if (empty($orderItems)) {
            return response()->json([
                'error' => 'No valid products found to place an order.',
                'missing_products' => $missingProducts
            ], 400);
        }

        // Step 2: Create the order
        $order = Order::create([
            'user_email' => $user->email ?? $request->delivery_details['recipientEmail'],
            'order_number' => $orderNumber,
            'status' => 'pending',
            'grand_total' => $grandTotal,
            'grand_total_ngn' => $grandTotalNGN,
            // 'shipping_cost' => 0.00,
            'item_count' => count($orderItems)
        ]);

        // Step 3: Save order items
        foreach ($orderItems as $item) {
            $item['order_id'] = $order->id;
            OrderItem::create($item);
        }

        if($request->has('promo_code')){
            $promo = AdminPromo::where('promo_code', $request->promo_code)->first();
            if($request->user()){
                if($promo){
                    $promo->users()->attach($user['id']);
                    $promo->updateMaxUses($promo->id);
                }
            }
        }

        // Step 4: Delivery details
        $deliveryDetails = $request->input('delivery_details', []);
        $deliveryDetails['recipientName'] = $user['full_name'];
        $deliveryDetails['email'] = $user['email'];
        $deliveryDetails['recipientPhone'] = $request->delivery_details['recipientPhone'] ?? $user['phone'] ?? null;
        $deliveryDetails['uniqueID'] = $orderNumber;
        $deliveryDetails['CustToken'] = $orderNumber;
        $deliveryDetails['BatchID'] = 'BATCH' . strtoupper(uniqid());
        $deliveryDetails['valueOfItem'] = $grandTotal;

        if($deliveryDetails['is_benin']){
            $deliveryDetails['destinationCountry'] = 'Benin';
        } else {
            if($deliveryDetails['is_nigeria']){
                try{
                    $result = $this->deliveryService->createDeliveryOrder($deliveryDetails) ?? 'seen';
                }
                catch(\Exception $e){
                    // throw new Exception('Failed to create delivery order: ' . $e->getMessage());
                }

            }else{
                try{
                    $result = $this->deliveryService->createExportOrder($deliveryDetails) ?? 'hey';
                }
                catch(\Exception $e){
                    // throw new Exception('Failed to create delivery order: ' . $e->getMessage());
                }
            }
        }

        $deliveryDetails['delivery_order_id'] = $result['orderNos'][$orderNumber] ?? null;

        //save delivery details to delivery table
        $delivery = Delivery::create(array_merge([
            'order_id' => $order->id,
            'delivery_order_id' => $deliveryDetails['delivery_order_id'] ?? null,
        ], $deliveryDetails));

        // Step 5: Return success response with warnings
        $response = [
            'status' => 'success',
            'message' => 'Order placed successfully',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'grand_total' => number_format($grandTotal, 2),
                'currency' => $returnCurrency,
                'items' => $orderItems,
                'delivery_details' => $deliveryDetails,
            ]
        ];

        if (!empty($missingProducts)) {
            $response['warning'] = 'Some products were not found and were skipped.';
            $response['missing_products'] = $missingProducts;
        }

        // $full_name = ($user->first_name . ' ' . $user->last_name) ?? $request->full_name;

        try {
            $this->notificationService->userNotification(
                $user,
                'Order',
                'Order Placed',
                'Order Placed.',
                'You have placed an order with ID: ' . $order->order_number . ' and delivery id: ' . $deliveryDetails['delivery_order_id'] . 'You can check on the status of this order on Rolinse. We will notify you when this order has been delivered',
                true,
                '/orders/' . $order->id,
                'View Order'
            );
        } catch (Exception $e) {
            // Log the error or handle it as needed
            // For now, we'll just ignore email sending failures
        }

        if ($request->authUser) {
            ActivityLogger::log(
                'Order',
                'Order Placed',
                'The order with ID: ' . $order->order_number . ' has been placed by ' . $user['full_name'],
                $user['id']
            );
        }

        // return response()->json($response, 201);
        return $this->success('Order placed successfully', $response, [], 201);
    }


    public function getOrderHistory(Request $request)
    {
        $user = $request->authUser;

        $orders = Order::with(['items.product', 'delivery'])
            ->where('user_email', $user->email ?? $request->email)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return $this->success('Order history retrieved successfully', $orders, [], 200);
    }


    public function getOrderDistribution()
    {
        $type = request()->get('type');
        if (!in_array($type, ['category', 'brand'])) {
            return response()->json(['message' => 'Invalid distribution type provided'], 400);
        }

        // Determine relationship based on `type`
        $relation = $type === 'category' ? 'items.product.category' : 'items.product.brand';

        // Fetch orders with the appropriate relationship
        $orders = Order::with($relation)
            ->where('status', 'completed')
            ->get();

        if ($orders->isEmpty()) {
            return $this->success('No completed orders found', [], [], 200);
        }

        // Aggregate distribution data
        $counts = $orders->flatMap(function ($order) use ($type) {
            return $order->items->map(function ($item) use ($type) {
                return $type === 'category'
                    ? $item->product->category->name ?? 'Uncategorized'
                    : $item->product->brand->name ?? 'Unbranded';
            });
        })->countBy();

        // Total count for percentage calculation
        $total = $counts->sum();

        // Format the data as an array of objects
        $distribution = $counts->map(function ($count, $name) use ($total) {
            return [
                'name' => $name,
                'count' => $count,
                'percentage' => number_format(($count / $total) * 100, 2),
            ];
        })->values(); // Convert to array

        //order meta data
        $completeOrders = Order::where('status', 'completed')->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();
        $failedOrders = Order::where('status', 'failed')->count();
        // $totalRevenue = Order::where('status', 'completed')->sum('grand_total');
        $totalRevenueNGN = Order::where('status', 'completed')->sum('grand_total_ngn');
        $totalOrders = Order::count();
        $orderMeta = [
            'complete_orders' => $completeOrders,
            'pending_orders' => $pendingOrders,
            'cancelled_orders' => $cancelledOrders,
            'failed_orders' => $failedOrders,
            'total_orders' => $totalOrders,
            'totalRevenueNGN' => $totalRevenueNGN,
        ];

        return response()->json([
            'message' => 'Order data fetched successfully',
            'total' => $total,
            'data' => $distribution,
            'order_meta' => $orderMeta,
        ], 200);
    }


    //admin get all orders, such that it get all or you can search by order id or user email
    public function getAllOrders(Request $request)
    {
        $orders = Order::with(['items.product', 'items.product.category', 'items.product.brand', 'delivery'])
            ->when($request->has('order_number'), function ($query) use ($request) {
                return $query->where('order_number', $request->order_number);
            })
            ->when($request->has('user_email'), function ($query) use ($request) {
                return $query->where('user_email', $request->user_email);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return $this->success('Orders retrieved successfully', $orders, [], 200);
    }


    //update order status
    public function updateOrderStatus(Request $request, $orderId)
    {
        $order = Order::find($orderId);
        if (!$order) {
            return $this->failure('Order not found', [], 404);
        }

        $order->status = $request->status;
        $order->save();

        return $this->success('Order status updated successfully', $order, [], 200);
    }


}
