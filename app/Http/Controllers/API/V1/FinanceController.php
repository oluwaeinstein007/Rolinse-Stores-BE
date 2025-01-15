<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExchangeRate;

class FinanceController extends Controller
{
    //
    public function getAllCurrency() {
        // Get all currency codes from the database
        $currencyCodes = ExchangeRate::all()->pluck('currencyCode')->toArray();

        // Load the currency code data from the JSON file
        $file = storage_path('CurrencyCode/currency_code.json');
        $currencyJson = json_decode(file_get_contents($file), true);

        $data = [];

        // Loop through the array of currency codes and find their details
        foreach ($currencyCodes as $code) {
            $upperCode = strtoupper($code);
            if (isset($currencyJson[$upperCode])) {
                $currencyInfo = $currencyJson[$upperCode];
                $data[] = [
                    'currency_code' => $upperCode,
                    'currency_name' => $currencyInfo['name'],
                    'currency_symbol' => $currencyInfo['symbol'],
                ];
            }
        }

        return response()->json([
            'message' => 'success',
            'data' => $data
        ], 200);
    }
}
