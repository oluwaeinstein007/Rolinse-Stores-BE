<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Exception;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Models\Delivery;
use App\Models\User;


class DeliveryController extends Controller
{
    protected $deliveryService;
    protected $notificationService;

    public function __construct(
        DeliveryService $deliveryService,
        NotificationService $notificationService
    ) {
        $this->deliveryService = $deliveryService;
        $this->notificationService = $notificationService;
    }

    public function authenticate()
    {
        try {
            $result = $this->deliveryService->authenticate();
            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function createDeliveryOrder(Request $request)
    {
        try {
            $request->validate([
                'recipient_address' => 'required|string',
                'recipient_state' => 'required|string',
                'recipient_name' => 'required|string',
                'recipient_phone' => 'required|string',
                'recipient_email' => 'nullable|email',
                'unique_id' => 'nullable|string',
                'batch_id' => 'nullable|string',
                'cust_token' => 'nullable|string',
                'item_description' => 'nullable|string',
                'additional_details' => 'nullable|string',
                'value_of_item' => 'required|string',
                'weight' => 'required|integer',
                'pickup_state' => 'nullable|string',
                'waybill_number' => 'nullable|string',
                'pickup_date' => 'nullable|date',
                'is_item_cod' => 'nullable|boolean'
            ]);

            $result = $this->deliveryService->createDeliveryOrder($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getOrderStatus($orderId)
    {
        try {
            // Will implement this method in DeliveryService
            $result = $this->deliveryService->getOrderStatus($orderId);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function calculateDeliveryCost(Request $request)
    {
        try {
            $request->validate([
                'state' => 'required|string',
                'pickUpState' => 'required|string',
                'weight' => 'required|integer',
            ]);

            $result = $this->deliveryService->calculateDeliveryCost($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getDeliveryOrder(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string'
            ]);

            $result = $this->deliveryService->getDeliveryOrder($request->order_id);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function searchOrders(Request $request)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'page' => 'required|integer',
                'order_no' => 'nullable|string',
                'recipient_name' => 'nullable|string',
                'recipient_phone' => 'nullable|string',
                'order_status' => 'nullable|string|in:Pending Pick-Up,Picked-Up,Dispatched,Delivered,Returned',
                'org_rep' => 'nullable|string'
            ]);

            $result = $this->deliveryService->searchOrders($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function trackOrder($orderNumber)
    {
        try {
            $result = $this->deliveryService->trackOrder($orderNumber);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateDeliveryOrder(Request $request)
    {
        try {
            $request->validate([
                'orders' => 'required|array|min:1',
                'orders.*.order_no' => 'required|string',
                'orders.*.recipient_address' => 'required|string',
                'orders.*.recipient_state' => 'required|string',
                'orders.*.recipient_name' => 'required|string',
                'orders.*.recipient_phone' => 'required|string',
            ]);

            $result = $this->deliveryService->updateDeliveryOrder($request->orders);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function calculateDeliveryTime(Request $request)
    {
        try {
            $request->validate([
                'delivery_type' => 'required|string|in:import,export,local',
                'pick_up_state' => 'required_if:delivery_type,local|string',
                'drop_off_state' => 'required_if:delivery_type,local|string'
            ]);

            $result = $this->deliveryService->calculateDeliveryTime($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getExportLocations()
    {
        try {
            $result = $this->deliveryService->getExportLocations();

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function calculateExportCost(Request $request)
    {
        try {
            $request->validate([
                'destinationState' => 'required|string',
                'weightId' => 'required|numeric',
                'exportLocationId' => 'required|string'
            ]);

            $result = $this->deliveryService->calculateExportCost($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function createExportOrder(Request $request)
    {
        try {
            $request->validate([
                'orders' => 'required|array|min:1',
                'orders.*.recipient_address' => 'required|string',
                'orders.*.recipient_name' => 'required|string',
                'orders.*.recipient_phone' => 'required|string',
                'orders.*.unique_id' => 'required|string',
                'orders.*.batch_id' => 'required|string',
                'orders.*.value_of_item' => 'required|string',
                'orders.*.weight' => 'required|integer',
                'orders.*.export_location_id' => 'required|integer',
                'orders.*.cust_token' => 'nullable|string',
                'orders.*.item_description' => 'nullable|string',
                'orders.*.additional_details' => 'nullable|string',
                'orders.*.third_party' => 'nullable|boolean',
                'orders.*.sender_name' => 'required_if:orders.*.third_party,true|string',
                'orders.*.sender_address' => 'required_if:orders.*.third_party,true|string',
                'orders.*.sender_phone' => 'required_if:orders.*.third_party,true|string'
            ]);

            $result = $this->deliveryService->createExportOrder($request->orders);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        try {
            $request->validate([
                'orderNumber' => 'required|string',
                'status' => 'required|string'
            ]);

            // Find the order in our system
            $delivery = Delivery::where('delivery_order_id', $request->orderNumber)
                ->first();

            // Update order status
            $delivery->delivery_status = $request->status;
            $delivery->save();

            $order = Order::where('id', $delivery->order_id)
                ->first();


            if (!$order) {
                Log::warning('Webhook: Order not found', [
                    'delivery_order_id' => $request->orderNumber,
                    'status' => $request->status
                ]);
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Update order status
            // $order->status = 'completed';
            // $order->save();

            $user = User::where('email', $order->user_email)->first();

            if($user){
                // Log the status change
                ActivityLogger::log(
                    'Order',
                    'Delivery Status Update',
                    "Order {$order->order_number} delivery status updated to: {$request->status}",
                    $user['id'] ?? null,
                );
            }

            $user =[
                'email' => $order->user_email,
                'full_name' => $user->first_name ?? $delivery->recipientName ?? null,
                'id' => $user['id'] ?? null,
            ];

            // Send notification to user if order exists
                $this->notificationService->userNotification(
                    $user,
                    'Order',
                    'Delivery Status Update',
                    'Delivery Status Updated',
                    "Your order {$order->order_number} with delivery id {$request->orderNumber} status has been updated to: {$request->status}",
                    true,
                    '/orders/' . $order->id,
                    'View Order'
                );

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully'
            ], 200);
        } catch (Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage(), [
                'order_number' => $request->orderNumber ?? null,
                'status' => $request->status ?? null
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process webhook'
            ], 500);
        }
    }
}
