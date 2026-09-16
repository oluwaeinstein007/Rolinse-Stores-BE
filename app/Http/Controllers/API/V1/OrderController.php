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
use Illuminate\Support\Facades\Log;

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
        // deliveries.recipientAddress/recipientState/weight/pickup_state are
        // NOT NULL with no default (2025_03_29_230616_create_deliveries_table.php)
        // and nothing here auto-derives them the way recipientName/email/
        // uniqueID/CustToken/BatchID/valueOfItem are below — an order missing
        // any of these previously reached the Delivery::create() insert and
        // crashed with a raw 500 QueryException instead of a clean 422.
        $request->validate([
            'delivery_details.recipientAddress' => 'required|string',
            'delivery_details.recipientState' => 'required|string',
            'delivery_details.weight' => 'required|numeric',
            'delivery_details.pickup_state' => 'required|string',
        ]);

        // $request->authUser is null for a guest order (this route is
        // `Optional` middleware, not auth:sanctum) — $user->email on null
        // previously warned-then-crashed (Laravel converts PHP warnings to
        // exceptions) before ever reaching the delivery_details fallback.
        // Request::input() with dot notation is null-safe for a missing
        // nested key, unlike the raw $request->delivery_details['key'] array
        // access this replaces, which crashed the same way if the client
        // didn't send that particular field.
        $authUser = $request->authUser;
        $user = [
            'email' => $authUser?->email ?? $request->input('delivery_details.recipientEmail'),
            'full_name' => trim(($authUser?->first_name ?? '') . ' ' . ($authUser?->last_name ?? ''))
                ?: $request->input('delivery_details.recipientName'),
            'id' => $authUser?->id,
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

            // `first()` returning null previously meant reading ->image_path
            // straight off null — PHP 8 doesn't crash on that (the ?? still
            // catches it) but it does emit an "attempt to read property on
            // null" warning on every miss. The null-safe operator says what's
            // actually meant: "if there's no match, there's no path".
            $image = ProductImage::where('product_id', $productId)
                    ->where('color_id', $productData['color_id'] ?? null)
                    ->first()
                    ?->image_path ?? ProductImage::where('product_id', $productId)
                    ->first()
                    ?->image_path ?? null;

            $color = Attribute::where('id', $productData['color_id'] ?? null)->first()?->value ?? null;


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
        // $user is the array rebuilt above (email/full_name/id), not the
        // original authUser model — ->email here was always null (property
        // access on an array just warns-then-null), which combined with the
        // same undefined-key risk on the raw array fallback crashed this on
        // any order that didn't explicitly send delivery_details.recipientEmail.
        // $user['email'] already carries the same authUser-or-recipientEmail
        // fallback correctly resolved a few lines up.
        $order = Order::create([
            'user_email' => $user['email'],
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

        // $result starts null and stays null if delivery creation fails below —
        // previously undefined in that case, which threw a warning reading
        // $result['orderNos'] and silently produced an order with no shipment
        // and no indication anything went wrong.
        $result = null;
        $deliveryCreationFailed = false;

        try {
            // is_benin/is_nigeria were read without a default, so a client that
            // omitted both (or sent neither as true) silently fell through to
            // the export-order branch for every order — defaulting explicitly
            // to "domestic Nigeria" here instead, the common case.
            if ($deliveryDetails['is_benin'] ?? false) {
                $deliveryDetails['destinationCountry'] = 'Benin';
            } elseif ($deliveryDetails['is_nigeria'] ?? true) {
                $result = $this->deliveryService->createDeliveryOrder($deliveryDetails);
            } else {
                $result = $this->deliveryService->createExportOrder($deliveryDetails);
            }
        } catch (\Exception $e) {
            $deliveryCreationFailed = true;
            Log::error('Failed to create delivery order for ' . $orderNumber . ': ' . $e->getMessage());
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

        if ($deliveryCreationFailed) {
            // The order itself is still valid/paid-for — surface this rather than
            // pretending a shipment was scheduled when it wasn't (previously
            // silent; see the logged error above for the underlying cause).
            $response['warning'] = trim(($response['warning'] ?? '') . ' Delivery scheduling failed and will need to be retried manually.');
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
        $email = $user->email ?? $request->email;

        // Without this, a request with neither a logged-in user nor an ?email=
        // param fell through to where('user_email', null) — returning every
        // guest order that has no email attached, to anyone who asked.
        if (!$email) {
            return $this->failure('Log in or provide an email to look up order history', [], 422);
        }

        $orders = Order::with(['items.product', 'delivery'])
            ->where('user_email', $email)
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
            ->paginate($request->per_page ?? 50);

        return $this->success('Orders retrieved successfully', $orders, [], 200);
    }


    //update order status
    public function updateOrderStatus(Request $request, $orderId)
    {
        // Previously accepted any string with no validation — a typo could
        // silently set a status that never matches the enum used everywhere
        // else (getOrderDistribution's completed/pending/cancelled/failed
        // counts, revenue totals), and nothing ever told the customer their
        // order status changed.
        $request->validate([
            'status' => 'required|string|in:pending,completed,cancelled,failed',
        ]);

        $order = Order::find($orderId);
        if (!$order) {
            return $this->failure('Order not found', [], 404);
        }

        $order->status = $request->status;
        $order->save();

        try {
            $this->notificationService->userNotification(
                ['email' => $order->user_email, 'id' => null],
                'Order',
                'Order Status Update',
                'Order Status Updated',
                "Your order {$order->order_number} status has been updated to: {$order->status}",
                true,
                '/orders/' . $order->id,
                'View Order'
            );
        } catch (Exception $e) {
            Log::error('Failed to send order status notification for ' . $order->order_number . ': ' . $e->getMessage());
        }

        return $this->success('Order status updated successfully', $order, [], 200);
    }


}
