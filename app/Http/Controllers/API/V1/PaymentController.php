<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\MakePaymentRequest;
use Illuminate\Http\Request;
use App\Services\StripeService;
use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
use App\Enums\PaymentGateway;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use App\Traits\RespondWithHttpStatus; // Added this use statement

class PaymentController extends Controller
{
    private $paystackSecretKey;
    private $paystackPaymentUrl;

    protected $flutterwaveSecretKey;
    protected $flutterwavePublicKey;
    protected $flutterwavePaymentUrl;

    protected $paypalClientId;
    protected $paypalClientSecret;
    protected $paypalMode;
    protected $paypalApiUrl;

    public function __construct()
    {
        // Paystack
        $this->paystackSecretKey = config('services.paystack.secret_key');
        $this->paystackPaymentUrl = config('services.paystack.payment_url');

        // Flutterwave
        $this->flutterwaveSecretKey = config('services.flutterwave.secret_key');
        $this->flutterwavePaymentUrl = config('services.flutterwave.payment_url');
        $this->flutterwavePublicKey = config('services.flutterwave.public_key');

        // PayPal
        $this->paypalClientId = config('services.paypal.client_id');
        $this->paypalClientSecret = config('services.paypal.client_secret');
        $this->paypalMode = config('services.paypal.mode', 'sandbox');
        $this->paypalApiUrl = $this->paypalMode === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
            : 'https://api-m.paypal.com/v2/checkout/orders';
    }


    // public function pay(MakePaymentRequest $request)
    public function pay(Request $request)
    {
        $user = $request->authUser;
        $paymentTransaction = Transaction::create([
            'reference' => strtoupper(
            str_replace('_', ' ', now()->timestamp . bin2hex(random_bytes(6)))),
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'USD',
            'user_email' => $user->email ?? $request->email,
            'order_id' => $request->order_id,
            'payment_gateway' => PaymentGateway::STRIPE->value, // Defaulting to Stripe for now
            'type' => TransactionType::ONEOFF->value,
            'status' => TransactionStatus::PENDING->value,
        ]);
        $stripe = new StripeService($paymentTransaction->reference);
        $response = $stripe->pay(amount: $request->amount);
        // $response['reference'] = $paymentTransaction->reference;
        return $this->success('Payment for the order initiated.', $response);
    }

    public function confirmPayment(Request $request)
    {
        if ($request->has(['reference', 'paymentIntentId', 'clientSecret']))
        {
            $reference = $request->input('reference');
            $paymentIntentId = $request->input('paymentIntentId');
            $clientSecret = $request->input('clientSecret');

            $stripeService = new StripeService($reference);
            $response = $stripeService->confirmPaymentIntent($paymentIntentId, $clientSecret);
            return $response;
        }
        else
        {
            return response()->json(['error' => "Required parameters missing."]);
        }

    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try
        {
            //Log::info('payload: '. $payload);
            //Log::info(message: 'sigheader: '. $sigHeader);
            //Log::info(message: 'secret: '. config('stripe.webhook_secret'));

            $event = \Stripe\Webhook::constructEvent(
               $payload,
                $sigHeader,
                config('stripe.webhook_secret')
            );


            //Handle the event based on its type
            switch ($event->type)
            {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    $stripeService = new StripeService();
                    $response[] = $stripeService->confirmPaymentIntent($paymentIntent->id, $paymentIntent->client_secret);
                    Log::info('Payment succeeded event: ' . $paymentIntent->id.' Response: ',$response);
                    break;

                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    Log::error('Payment failed: ' . $paymentIntent->id);
                    break;

                default:
                    Log::warning('Unhandled event type: ' . $event->type);
                    break;
            }

            return response('Webhook Handled', 200);
        }
        catch (\UnexpectedValueException $e)
        {
            Log::error('Invalid payload');
            return response('Invalid payload', 400);

        }
        catch (\Stripe\Exception\SignatureVerificationException $e)
        {
            Log::error('Invalid signature');
            return response('Invalid signature', 400);
        }
    }






    /* -----------------  Paystcak Methods ----------------- */

    /**
     * Initiate a Paystack payment.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiatePayment(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'amount' => 'required|numeric',
            'order_id' => 'required|numeric',
        ]);

        $client = new Client();

        try {
            $response = $client->post("{$this->paystackPaymentUrl}/transaction/initialize", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'email' => $request->email,
                    'amount' => $request->amount * 100, // Paystack expects amount in kobo
                    'currency' => $request->currency ?? 'NGN',
                    'callback_url' => env('Frontend_Callback')
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            //handle save to transaction table
            $transaction = Transaction::create([
                'reference' => $responseData['data']['reference'],
                'payment_id' => $responseData['data']['reference'],
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'NGN',
                'user_email' => $user->email ?? $request->email,
                'order_id' => $request->order_id,
                'payment_gateway' => PaymentGateway::PAYSTACK->value,
                'type' => TransactionType::ONEOFF->value,
                'status' => TransactionStatus::PENDING->value,
            ]);

            $responseData['data']['transaction'] = $transaction;

            return response()->json([
                'status' => 'success',
                'data' => $responseData['data'],
            ]);
        } catch (\Exception $e) {
            Log::error('Paystack Initiate Payment Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to initiate payment. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify a Paystack payment.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $client = new Client();

        try {
            $response = $client->get("{$this->paystackPaymentUrl}/transaction/verify/{$request->reference}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            //get the transaction from the database
            $transaction = Transaction::where('reference', $request->reference)->first();
            //update the transaction status
            $transaction->status = $responseData['data']['status'] === 'success' ? TransactionStatus::COMPLETED->value : TransactionStatus::REJECTED->value;
            $transaction->save();

            if ($responseData['status'] === true && $responseData['data']['status'] === 'success') {
                //handle save to transaction table


                // Payment was successful
                return response()->json([
                    'status' => 'success',
                    'data' => $responseData['data'],
                ]);
            } else {
                // Payment failed
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification failed.',
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Paystack Verify Payment Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to verify payment. Please try again.',
            ], 500);
        }
    }






    /* -----------------  Flutterwave Methods ----------------- */

    /**
     * Initiate a Flutterwave payment.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

     public function startPayment(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'amount' => 'required|numeric',
            'order_id' => 'required|numeric',
            // 'currency' is not required as it defaults to 'NGN'
        ]);

        $client = new Client();
        // 1. Generate tx_ref BEFORE the API call and store it.
        $generatedTxRef = uniqid('flw_');

        try {
            $response = $client->post("{$this->flutterwavePaymentUrl}/payments", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->flutterwaveSecretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'tx_ref' => $generatedTxRef, // Use the stored reference
                    'amount' => $request->amount,
                    'currency' => $request->currency ?? 'NGN',
                    'payment_options' => 'card,account,ussd',
                    'redirect_url' => env('Frontend_Callback'),
                    'customer' => [
                        'email' => $request->email,
                        'name' => $request->name ?? 'Customer',
                    ],
                    'customizations' => [
                        'title' => config('app.name'),
                        'description' => 'Payment for order #' . $request->order_id,
                    ],
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            // 2. IMPORTANT: Check for API status success before proceeding
            if (!isset($responseData['status']) || $responseData['status'] !== 'success' || !isset($responseData['data'])) {
                // Log the actual error message from Flutterwave
                $errorMessage = $responseData['message'] ?? 'API did not return a success status or missing data.';
                Log::error('Flutterwave Initiate Payment API Error: ' . $errorMessage . ' Response: ' . json_encode($responseData));

                // Return a more specific error to the client
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment initiation failed with error from gateway: ' . $errorMessage,
                ], 500);
            }

            // 3. Save to transaction table using the locally generated tx_ref
            $transaction = Transaction::create([
                'reference' => $generatedTxRef, // USE THE LOCALLY GENERATED TX_REF
                'payment_id' => $responseData['data']['flw_ref'] ?? null,
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'NGN',
                'user_email' => $request->user()->email ?? $request->email,
                'order_id' => $request->order_id,
                'payment_gateway' => PaymentGateway::FLUTTERWAVE->value,
                'type' => TransactionType::ONEOFF->value,
                'status' => TransactionStatus::PENDING->value,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => array_merge($responseData['data'], [
                    'transaction' => $transaction,
                    'payment_link' => $responseData['data']['link'] ?? null,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Flutterwave Initiate Payment Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to initiate payment due to network or Guzzle error.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Verify a Flutterwave payment.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPayment(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|string',
        ]);

        $client = new Client();

        try {
            $response = $client->get("{$this->flutterwavePaymentUrl}/transactions/{$request->transaction_id}/verify", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->flutterwaveSecretKey,
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            // Get the transaction from the database
            $transaction = Transaction::where('payment_id', $request->transaction_id)
                ->orWhere('reference', $request->transaction_id)
                ->first();

            if ($transaction) {
                // Update the transaction status
                $transaction->status = $responseData['data']['status'] === 'successful'
                    ? TransactionStatus::COMPLETED->value
                    : TransactionStatus::REJECTED->value;
                $transaction->save();
            }

            if ($responseData['status'] === 'success' && $responseData['data']['status'] === 'successful') {
                return response()->json([
                    'status' => 'success',
                    'data' => $responseData['data'],
                    'transaction' => $transaction,
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification failed.',
                    'data' => $responseData['data'] ?? null,
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Flutterwave Verify Payment Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to verify payment. Please try again.',
            ], 500);
        }
    }

    /**
     * Handle Flutterwave webhook
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleWebhook(Request $request)
    {
        $signature = $request->header('verif-hash');
        $secretHash = config('services.flutterwave.secret_hash');

        if (!$signature || $signature !== $secretHash) {
            Log::error('Flutterwave webhook unauthorized access attempt');
            abort(401);
        }

        $payload = $request->all();

        try {
            $transaction = Transaction::where('payment_id', $payload['data']['flw_ref'])
                ->orWhere('reference', $payload['data']['tx_ref'])
                ->first();

            if ($transaction) {
                $transaction->status = $payload['data']['status'] === 'successful'
                    ? TransactionStatus::COMPLETED->value
                    : TransactionStatus::REJECTED->value;
                $transaction->save();

                // Trigger any post-payment actions here
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('Flutterwave Webhook Error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    /* -----------------  PayPal Methods ----------------- */

    /**
     * Initiate a PayPal payment.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiatePaypalPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'order_id' => 'required|numeric',
            'currency' => 'required|string',
        ]);

        if (!$this->paypalClientId || !$this->paypalClientSecret) {
            return $this->failure('PayPal API credentials not configured.', null, 500);
        }

        $client = new Client();

        try {
            // Create a transaction record first
            $transaction = Transaction::create([
                'reference' => strtoupper(str_replace('_', ' ', bin2hex(random_bytes(8)))), // Unique reference for PayPal
                'amount' => $request->amount,
                'currency' => $request->currency,
                'user_email' => $request->user()->email ?? $request->email, // Assuming user is authenticated
                'order_id' => $request->order_id,
                'payment_gateway' => PaymentGateway::PAYPAL->value,
                'type' => TransactionType::ONEOFF->value,
                'status' => TransactionStatus::PENDING->value,
            ]);

            // Get PayPal access token
            $baseUrl = $this->paypalMode === 'sandbox'
                    ? 'https://api-m.sandbox.paypal.com'
                    : 'https://api-m.paypal.com';

            $authResponse = $client->post("{$baseUrl}/v1/oauth2/token", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
                'auth' => [$this->paypalClientId, $this->paypalClientSecret],
            ]);

            $authData = json_decode($authResponse->getBody()->getContents(), true);
            $accessToken = $authData['access_token'];

            // Create PayPal order
            $orderResponse = $client->post($this->paypalApiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => $transaction->reference,
                            'amount' => [
                                'value' => $request->amount,
                                'currency_code' => $request->currency,
                            ],
                        ],
                    ],
                    'application_context' => [
                        'return_url' => env('Frontend_Callback') . '/payment/success?transaction_ref=' . $transaction->reference,
                        'cancel_url' => env('Frontend_Callback') . '/payment/cancel?transaction_ref=' . $transaction->reference,
                        'brand_name' => config('app.name'),
                    ],
                ],
            ]);

            $orderData = json_decode($orderResponse->getBody()->getContents(), true);

            // Update transaction with PayPal order ID
            $transaction->update(['payment_id' => $orderData['id']]);

            return $this->success('PayPal payment initiated.', [
                'paypal_order_id' => $orderData['id'],
                'paypal_approve_url' => $orderData['links'][1]['href'], // Assuming the second link is the approval URL
                'transaction_reference' => $transaction->reference,
            ]);

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('PayPal Initiate Payment Error: ' . $e->getMessage());
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                Log::error('PayPal API Response: ' . $responseBody);
                return $this->failure('PayPal API error: ' . $responseBody, null, $e->getResponse()->getStatusCode());
            }
            return $this->failure('Unable to initiate PayPal payment. Please try again.', null, 500);
        } catch (\Exception $e) {
            Log::error('PayPal Initiate Payment General Error: ' . $e->getMessage());
            return $this->failure('Unable to initiate PayPal payment. Please try again.', null, 500);
        }
    }

    /**
     * Verify a PayPal payment.
     * This method would be called after the user completes the PayPal flow.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPaypalPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string', // This is the PayPal order ID
            'transaction_reference' => 'required|string', // Our internal transaction reference
        ]);

        $client = new Client();

        try {
            // Get PayPal access token
            $authResponse = $client->post("https://api-m.{$this->paypalMode}.paypal.com/v1/oauth2/token", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
                'auth' => [$this->paypalClientId, $this->paypalClientSecret],
            ]);

            $authData = json_decode($authResponse->getBody()->getContents(), true);
            $accessToken = $authData['access_token'];

            // Capture the PayPal order to finalize the payment
            $captureUrl = "https://api-m.{$this->paypalMode}.paypal.com/v2/checkout/orders/{$request->order_id}/capture";
            $captureResponse = $client->post($captureUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $captureData = json_decode($captureResponse->getBody()->getContents(), true);

            // Find the transaction in our database
            $transaction = Transaction::where('reference', $request->transaction_reference)->first();

            if ($transaction) {
                if ($captureData['status'] === 'COMPLETED') {
                    $transaction->status = TransactionStatus::COMPLETED->value;
                    $transaction->payment_id = $request->order_id; // Store PayPal order ID
                    $transaction->save();
                    return $this->success('PayPal payment completed successfully.', $transaction);
                } else {
                    $transaction->status = TransactionStatus::REJECTED->value;
                    $transaction->save();
                    return $this->failure('PayPal payment capture failed or was not completed.', null, 400);
                }
            } else {
                return $this->failure('Transaction not found.', null, 404);
            }

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('PayPal Verify Payment Error: ' . $e->getMessage());
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                Log::error('PayPal API Response: ' . $responseBody);
                return $this->failure('PayPal API error during verification: ' . $responseBody, null, $e->getResponse()->getStatusCode());
            }
            return $this->failure('Unable to verify PayPal payment. Please try again.', null, 500);
        } catch (\Exception $e) {
            Log::error('PayPal Verify Payment General Error: ' . $e->getMessage());
            return $this->error('Unable to verify PayPal payment. Please try again.', 500);
        }
    }

    // Add a method to handle the payment gateway selection if needed
    // For example, a new endpoint that takes the gateway and amount, then calls the appropriate initiate method.
    // For now, we'll assume the frontend calls the specific initiate methods.

}
