<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case STRIPE = 'stripe';
    case PAYSTACK = 'paystack';
    case PayPal = 'paypal';
}
