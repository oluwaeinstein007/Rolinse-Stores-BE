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

class PaymentController extends Controller
{
    private $paystackSecretKey;
    private $paystackPaymentUrl;

    protected $flutterwaveSecretKey;
    protected $flutterwavePaymentUrl;

    public function __construct()
    {
        // $this->paystackSecretKey = env('PAYSTACK_SECRET_KEY');
        // $this->paystackPaymentUrl = env('PAYSTACK_PAYMENT_URL');
        $this->paystackSecretKey = config('services.paystack.secret_key');
        $this->paystackPaymentUrl = config('services.paystack.payment_url');
        $this->flutterwaveSecretKey = config('services.flutterwave.secret_key');
        $this->flutterwavePaymentUrl = config('services.flutterwave.payment_url');
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
            'payment_gateway' => PaymentGateway::STRIPE->value,
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
        ]);

        $client = new Client();

        try {
            $response = $client->post("{$this->flutterwavePaymentUrl}/payments", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->flutterwaveSecretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'tx_ref' => uniqid('flw_'),
                    'amount' => $request->amount,
                    'currency' => $request->currency ?? 'NGN',
                    'payment_options' => 'card,account,ussd',
                    'redirect_url' => env('FRONTEND_CALLBACK_URL'),
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

            // Save to transaction table
            $transaction = Transaction::create([
                'reference' => $responseData['data']['tx_ref'],
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
            Log::error('Flutterwave Initiate Payment Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to initiate payment. Please try again.',
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


}
