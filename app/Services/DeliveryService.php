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
}
