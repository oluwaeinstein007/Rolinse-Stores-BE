<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class DeliveryService
{
    protected $baseUrl;
    protected $userId;
    protected $password;
    protected $authKey;
    protected $bearerToken;

    public function __construct()
    {
        $this->baseUrl = env('FEZ_BASE_URL');
        $this->userId = env('FEZ_USER_ID');
        $this->password = env('FEZ_PASSWORD');
        $this->authKey = env('FEZ_AUTH_KEY');
    }

    public function authenticate()
    {
        try {
            $response = Http::post($this->baseUrl . '/user/authenticate', [
                'user_id' => $this->userId,
                'password' => $this->password
            ]);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            $data = $response->json();

            // Fix: Store the actual token values
            $bearerToken = $data['authDetails']['authToken'];
            $secretKey = $data['orgDetails']['secret-key'];

            // Cache the credentials for 23 hours (token expires in 24 hours)
            Cache::put('fez_bearer_token', $bearerToken, now()->addHours(23));
            Cache::put('fez_secret_key', $secretKey, now()->addHours(23));

            return $data;
        } catch (Exception $e) {
            throw new Exception('Authentication failed: ' . $e->getMessage());
        }
    }

    protected function getAuthenticatedRequest()
    {
        $bearerToken = Cache::get('fez_bearer_token');
        $secretKey = Cache::get('fez_secret_key');

        // If no token in cache, authenticate
        if (!$bearerToken || !$secretKey) {
            $this->authenticate();
            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');
        }

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $bearerToken,
            'secret-key' => $secretKey
        ]);
    }

    public function calculateDeliveryFee($pickupLocation, $deliveryLocation, $vehicleType)
    {
        try {
            $response = $this->getAuthenticatedRequest()
                ->post($this->baseUrl . '/calculate-fee', [
                    'pickup' => [
                        'latitude' => $pickupLocation['latitude'],
                        'longitude' => $pickupLocation['longitude']
                    ],
                    'delivery' => [
                        'latitude' => $deliveryLocation['latitude'],
                        'longitude' => $deliveryLocation['longitude']
                    ],
                    'vehicleType' => $vehicleType
                ]);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to calculate delivery fee: ' . $e->getMessage());
        }
    }

    public function createDeliveryOrder($data)
    {
        try {
            $payload = [[  // API expects an array of orders
                'recipientAddress' => $data['recipient_address'],
                'recipientState' => $data['recipient_state'],
                'recipientName' => $data['recipient_name'],
                'recipientPhone' => $data['recipient_phone'],
                'recipientEmail' => $data['recipient_email'] ?? null,
                'uniqueID' => $data['unique_id'] ?? uniqid('order-'),
                'BatchID' => $data['batch_id'] ?? uniqid('batch-'),
                'CustToken' => $data['cust_token'] ?? null,
                'itemDescription' => $data['item_description'] ?? null,
                'additionalDetails' => $data['additional_details'] ?? null,
                'valueOfItem' => $data['value_of_item'],
                'weight' => $data['weight'],
                'pickUpState' => $data['pickup_state'] ?? null,
                'waybillNumber' => $data['waybill_number'] ?? null,
                'pickUpDate' => $data['pickup_date'] ?? null,
                'isItemCod' => $data['is_item_cod'] ?? false,
            ]];

            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->post($this->baseUrl . '/order', $payload);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to create delivery order: ' . $e->getMessage());
        }
    }

    public function getOrderStatus($orderId)
    {
        try {
            $response = $this->getAuthenticatedRequest()
                ->get($this->baseUrl . '/status/' . $orderId);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to get order status: ' . $e->getMessage());
        }
    }

    public function calculateDeliveryCost($data)
    {
        try {
            $payload = [
                'state' => $data['state'],
                'pickUpState' => $data['pickUpState'],
                'weight' => $data['weight'],
            ];

            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->post($this->baseUrl . '/order/cost', $payload);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to calculate delivery cost: ' . $e->getMessage());
        }
    }

    public function getDeliveryOrder($orderId)
    {
        try {
            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->get($this->baseUrl . '/order/' . $orderId);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to get delivery order: ' . $e->getMessage());
        }
    }

    public function searchOrders($data)
    {
        try {
            $payload = [
                'startDate' => $data['start_date'],
                'endDate' => $data['end_date'],
                'page' => $data['page']
            ];

            // Add optional parameters if they exist
            if (isset($data['order_no'])) {
                $payload['orderNo'] = $data['order_no'];
            }
            if (isset($data['recipient_name'])) {
                $payload['recipientName'] = $data['recipient_name'];
            }
            if (isset($data['recipient_phone'])) {
                $payload['recipientPhone'] = $data['recipient_phone'];
            }
            if (isset($data['order_status'])) {
                $payload['orderStatus'] = $data['order_status'];
            }
            if (isset($data['org_rep'])) {
                $payload['OrgRep'] = $data['org_rep'];
            }

            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->post($this->baseUrl . '/orders/search', $payload);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to search orders: ' . $e->getMessage());
        }
    }

    public function trackOrder($orderNumber)
    {
        try {
            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->get($this->baseUrl . '/order/track/' . $orderNumber);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to track order: ' . $e->getMessage());
        }
    }

    public function updateDeliveryOrder($orders)
    {
        try {
            // Format the orders array to match Fez API requirements
            $payload = array_map(function($order) {
                return [
                    'orderNo' => $order['order_no'],
                    'recipientAddress' => $order['recipient_address'],
                    'recipientState' => $order['recipient_state'],
                    'recipientName' => $order['recipient_name'],
                    'recipientPhone' => $order['recipient_phone']
                ];
            }, $orders);

            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->put($this->baseUrl . '/order', $payload);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to update delivery order: ' . $e->getMessage());
        }
    }

    public function calculateDeliveryTime($data)
    {
        try {
            $payload = [
                'delivery_type' => $data['delivery_type']
            ];

            // Add optional parameters for local delivery
            if ($data['delivery_type'] === 'local') {
                $payload['pick_up_state'] = $data['pick_up_state'];
                $payload['drop_off_state'] = $data['drop_off_state'];
            }

            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->post($this->baseUrl . '/delivery-time-estimate', $payload);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to calculate delivery time: ' . $e->getMessage());
        }
    }

    public function getExportLocations()
    {
        try {
            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->get($this->baseUrl . '/export-locations');

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to get export locations: ' . $e->getMessage());
        }
    }

    public function calculateExportCost($data)
    {
        try {
            $payload = [
                'destinationState' => $data['destinationState'],
                'weightId' => $data['weight'],
                'exportLocationId' => $data['exportLocationId']
            ];

            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->post($this->baseUrl . '/export-delivery-cost', $payload);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to calculate export cost: ' . $e->getMessage());
        }
    }

    public function createExportOrder($orders)
    {
        try {
            // Format the orders array to match Fez API requirements
            $payload = array_map(function($order) {
                $formattedOrder = [
                    'recipientAddress' => $order['recipient_address'],
                    'recipientName' => $order['recipient_name'],
                    'recipientPhone' => $order['recipient_phone'],
                    'uniqueID' => $order['unique_id'],
                    'BatchID' => $order['batch_id'],
                    'valueOfItem' => $order['value_of_item'],
                    'weight' => $order['weight'],
                    'exportLocationId' => $order['export_location_id']
                ];

                // Add optional fields if they exist
                if (isset($order['cust_token'])) {
                    $formattedOrder['CustToken'] = $order['cust_token'];
                }
                if (isset($order['item_description'])) {
                    $formattedOrder['itemDescription'] = $order['item_description'];
                }
                if (isset($order['additional_details'])) {
                    $formattedOrder['additionalDetails'] = $order['additional_details'];
                }

                // Add third party sender details if specified
                if (isset($order['third_party']) && $order['third_party']) {
                    $formattedOrder['thirdparty'] = 'true';
                    $formattedOrder['senderName'] = $order['sender_name'];
                    $formattedOrder['senderAddress'] = $order['sender_address'];
                    $formattedOrder['senderPhone'] = $order['sender_phone'];
                }

                return $formattedOrder;
            }, $orders);

            $bearerToken = Cache::get('fez_bearer_token');
            $secretKey = Cache::get('fez_secret_key');

            if (!$bearerToken || !$secretKey) {
                $this->authenticate();
                $bearerToken = Cache::get('fez_bearer_token');
                $secretKey = Cache::get('fez_secret_key');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'secret-key' => $secretKey
            ])->post($this->baseUrl . '/orders/export', $payload);

            if (!$response->successful()) {
                throw new Exception($response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception('Failed to create export order: ' . $e->getMessage());
        }
    }
}
