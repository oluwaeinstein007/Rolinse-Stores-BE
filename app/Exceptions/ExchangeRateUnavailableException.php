<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The forex API (GeneralService::newCurrency) is only ever called when a
 * currency isn't already cached in the exchange_rates table — but when it
 * IS called and fails (network error, bad API key, provider outage), the
 * failure previously propagated as a raw, uncaught GuzzleException all the
 * way to a 500 with a full stack trace, on the checkout path (placeOrder),
 * price confirmation (confirmPrice), and the currency list (FinanceController).
 * This gives those callers something clean to catch/render instead.
 */
class ExchangeRateUnavailableException extends RuntimeException
{
    //
}
