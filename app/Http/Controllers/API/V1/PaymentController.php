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

class PaymentController extends Controller
{
    // public function pay(MakePaymentRequest $request)
    public function pay(Request $request)
    {
        $user = $request->authUser;
        $paymentTransaction = Transaction::create([
            'reference' => strtoupper(
            str_replace('_', ' ', now()->timestamp . bin2hex(random_bytes(6)))),
            'amount' => $request->amount,
            'user_email' => $user->email ?? $request->email,
            'payment_gateway' => PaymentGateway::STRIPE->value,
            'type' => TransactionType::ONEOFF->value,
            'status' => TransactionStatus::PENDING->value,
        ]);
        $stripe = new StripeService($paymentTransaction->reference);
        $response = $stripe->pay(amount: $request->amount);
        return $this->success('Milestone update successfully.', $response);
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
}
