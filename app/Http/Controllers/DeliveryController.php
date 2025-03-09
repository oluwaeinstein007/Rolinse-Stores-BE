<?php

namespace App\Http\Controllers;

use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Exception;

class DeliveryController extends Controller
{
    protected $deliveryService;

    public function __construct(DeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
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

    public function calculateDeliveryFee(Request $request)
    {
        try {
            $request->validate([
                'pickup_location' => 'required|array',
                'pickup_location.latitude' => 'required|numeric',
                'pickup_location.longitude' => 'required|numeric',
                'delivery_location' => 'required|array',
                'delivery_location.latitude' => 'required|numeric',
                'delivery_location.longitude' => 'required|numeric',
                'vehicle_type' => 'required|string'
            ]);

            // Will implement this method in DeliveryService
            $result = $this->deliveryService->calculateDeliveryFee(
                $request->pickup_location,
                $request->delivery_location,
                $request->vehicle_type
            );

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
}
