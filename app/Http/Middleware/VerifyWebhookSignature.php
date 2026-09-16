<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Guards inbound delivery-status webhooks (e.g. Fez Delivery) with a shared
 * secret, since that provider doesn't document an HMAC/signature scheme the
 * way Stripe/Flutterwave do (see PaymentController::webhook for the real
 * thing). This was previously a placeholder that always returned true —
 * i.e. the route it's attached to had no protection at all. Register the
 * same value as a query param or header with the provider's webhook config;
 * rotate WEBHOOK_SHARED_SECRET if it's ever exposed.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->isValidSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }

    private function isValidSignature(Request $request): bool
    {
        $expected = config('services.webhook_shared_secret');

        if (!$expected) {
            // Fail closed: an unconfigured secret must never be treated as "any request is valid".
            return false;
        }

        $provided = $request->header('X-Webhook-Secret') ?? $request->query('secret');

        return is_string($provided) && hash_equals($expected, $provided);
    }
}
