<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case PAYPAL = 'PayPal';
    case STRIPE = 'Stripe';
    case BANK_TRANSFER = 'Bank Transfer';
    case PAYSTACK = 'Paystack';
    case FLUTTERWAVE = 'Flutterwave';
}
