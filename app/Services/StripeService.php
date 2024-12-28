<?php

namespace App\Services;

use Stripe\StripeClient;
use App\Enums\TransactionStatus;
use App\Enums\PaymentGateway;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected $stripe;
    private $transactionReference;

    public function __construct($reference = null)
    {
        $this->transactionReference = $reference;
        $this->stripe = new StripeClient(config('stripe.secret_key'));
    }

    public function pay(float $amount): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amount * 100, //Convert the amount to cent
                'currency' => 'usd',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            if ($paymentIntent->client_secret != null) {
                //Update the transaction data to store payment id and initial status
                $paymentTransaction = Transaction::query()
                    ->where('reference', $this->transactionReference)
                    // ->where('payment_gateway', PaymentGateway::STRIPE)
                    ->first();
                $paymentTransaction->payment_id = $paymentIntent->id;
                $paymentTransaction->save();

                return [
                    'client_secret' => $paymentIntent->client_secret,
                    'reference' => $this->transactionReference
                ];
            } else {
                return ['error' => 'Client secret is missing.'];
            }
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }


    public function confirmPaymentIntent($paymentId, $clientSecret)
    {
        $data = $this->stripe->paymentIntents->retrieve($paymentId);
        $transactionReference = $this->transactionReference;

        if ($data->id == $paymentId && $data->client_secret == $clientSecret) {
            DB::beginTransaction();

            try {
                // Get the payment transaction
                $paymentTransaction = Transaction::query()
                    // ->where('payment_gateway', operator: PaymentGateway::STRIPE)
                    ->where(function($query) use ($transactionReference, $paymentId) {
                        $query->where('reference', $transactionReference)
                        ->orWhere('payment_id', $paymentId);
                    })
                    ->firstOrFail();

                    if ($paymentTransaction->status !== TransactionStatus::COMPLETED) {
                        $paymentTransaction->payment_id = $paymentId;
                        // $paymentTransaction->payment_method = $data->payment_method_types[0];
                        $paymentTransaction->status = TransactionStatus::tryFrom($data->status) ?? TransactionStatus::REJECTED;
                        if ($data->status === TransactionStatus::COMPLETED->value) {
                            //$paymentTransaction->narration = 'Approved';
                        }
                        $paymentTransaction->saveOrFail();

                        // Exit if the payment transaction is not successful
                        if ($paymentTransaction->status === TransactionStatus::COMPLETED) {
                            // Proceed to main course of action
                            // $wallet = Wallet::where('user_id', $paymentTransaction->user_id)->first();
                            // if ($wallet) {
                            //     // update the amount by adding it to the amount
                            //     $wallet->amount = $paymentTransaction->amount + $wallet->amount;
                            //     $wallet->save();
                            // }
                        }
                    } else {
                        return ['error' => false, 'message' => 'Transaction status is currently: '.TransactionStatus::COMPLETED->value];
                    }


                DB::commit();
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();

                Log::error('An invalid argument exception occurred.', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);

                return;
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                DB::rollBack();

                Log::error('Model not found.', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);

                return;
            } catch (\PDOException $e) {
                DB::rollBack();

                Log::error('A database query exception occurred.', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);

                return;
            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('An exception occurred.', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);

                return;
            }

            return ['error' => false, 'message' => 'payment was successful.'];
        } else {
            return ['error' => true, 'message' => "Required parameters missing."];
        }
    }

    public function updatePayments($transaction)
    {
        try {
            //Get the data from stripe
            $response = $this->stripe->paymentIntents->retrieve($transaction->payment_id);
            if ($response) {
                $COMPLETED = TransactionStatus::COMPLETED->value;
                //Check if the payment response from stripe is successful.
                if ($response->status == $COMPLETED) {
                    //Check if the transaction has not yet been successful.
                    if($transaction->status->value != $COMPLETED) {
                        $transaction->status = TransactionStatus::COMPLETED;
                        $transaction->save();

                        // $wallet = Wallet::where('user_id', $transaction->user_id)->first();
                        // if ($wallet) {
                        //     // update the amount by adding it to the amount
                        //     $wallet->amount = $transaction->amount + $wallet->amount;
                        //     $wallet->save();
                        // }
                    }

                } else {
                    // CANCEL THE PAYMENT TRANSACTION AND ORDER.
                    $transaction->status = TransactionStatus::CANCELLED;
                    $transaction->save();
                }
            }

            return;
        } catch (\Exception $e) {
            Log::error('An exception occurred.', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return;
        }
    }
}
