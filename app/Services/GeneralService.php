<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Level;
use App\Models\Setting;
use App\Models\Transaction;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use GuzzleHttp\Client;

use App\Models\ExchangeRate;
use App\Models\ExchangeHistory;

class GeneralService
{
    /**
     * Perform some general service logic.
     *
     * @param mixed $data
     * @return mixed
     */

    public function generateReferralCode($name){
        $code = Str::random(5); // You can adjust the length as needed
        $referalCode = $name. '-' . $code;
        return $referalCode;
    }


    public function generateRolinseId(){
        $timeNow = (microtime(true)*10000);
        return 'REF'.$timeNow;
    }


    public function roundNum($num) {
        return number_format($num, 2, '.', '');
    }


    public function decryptService ($code){
        $decrypted = Crypt::decryptString($code);
        return $decrypted;
    }


    public function encryptService ($value){
        $encrypt = Crypt::encryptString($value);
        return $encrypt;
    }


    public function calculateCharges($amount, $bookingCurrency){
        $charges = 0.015 * $amount;
        $charges = $this->roundNum($charges);
        return $charges;

        $totalTVA = $amount;
        $commission = $totalTVA * 0.05;
        $totalTVA = $totalTVA * $commission;

        $ppc = 0;
        $ppcCharges = 100;
        $baseCode =$bookingCurrency;
        $priceValue = $ppcCharges;
        $Convert = $this->convertMoney('NGN', $priceValue, $baseCode);
        if($bookingCurrency == 'NGN' && $totalTVA <= 126667) {
          $ppc = ($totalTVA * 0.015) + $Convert['convertPrice'];
        } else if($bookingCurrency == 'NGN' && $totalTVA > 126667) {
          $ppc = 2000;
        } else {
          $ppc = ($totalTVA * 0.039) + $Convert['convertPrice'];
        }

        $sc = 1000;
        $priceValue = $sc;
        $Convert = $this->convertMoney('NGN', $priceValue, $baseCode);
        $sc = $Convert['convertPrice'];
        $vat = ($sc + $commission) * 0.075;
        $wt = ($sc + $commission) * 0.05;
        $total = $totalTVA + $commission + $ppc + $sc + $vat + $wt;
        $totalTVA = $total;
        $gain = $commission - ($ppc + $vat + $wt);
    }


    public function convertMoney($baseCode, $amount, $returnBaseCode){
        $incomingExchangeRate = $this->getExchangeRate($baseCode);
        $outgoingExchangeRate = $this->getExchangeRate($returnBaseCode);

        if ($incomingExchangeRate === null || $outgoingExchangeRate === null) {
            throw new Exception("Invalid currency code(s).");
        }

        $result = $amount * ($outgoingExchangeRate / $incomingExchangeRate);
        $result = $this->roundNum($result);
        return $result;
    }


    public function getExchangeRate($currencyCode){
        $exchangeRate = ExchangeRate::where('currencyCode', $currencyCode)->value('rate');

        if ($exchangeRate === null) {
            $exchangeRate = $this->newCurrency($currencyCode);
            $this->addNewCurrency($currencyCode,$exchangeRate);
        }
        return $exchangeRate;
        // return $exchangeRate !== null ? $exchangeRate : 1;
    }


    public function newCurrency($currencyCode){
        $client = new Client();
        $url = 'https://api.apilayer.com/fixer/convert';
        $apikey = env('FOREX_API_KEY');

        $toCode = $currencyCode;
        $fromCode = 'USD';
        $headers = [
        'Content-Type' => 'text/plain',
        'apikey' => $apikey
        ];
        $params = [
            'to' => $toCode,
            'from' => $fromCode,
            'amount' => 1
        ];
        $response = $client->request('GET', $url, [
            'query' => $params,
            'headers' => $headers
        ]);

            $responseBody = $response->getBody()->getContents();
            $jsonData = json_decode($responseBody, true);

            $rate = $jsonData['info']['rate'];
            $rate = $this->roundNum($rate);
            return $rate;

    }


    public function addNewCurrency($currencyCode,$rate){
        $exchangeRate = new ExchangeRate();
        // $exchangeRate->user_id = 1;
        $exchangeRate->currencyCode = $currencyCode;
        $exchangeRate->rate = $rate;
        $exchangeRate->save();

        $exchangeHistory = new ExchangeHistory();
        // $exchangeHistory->user_id = 1;
        $exchangeHistory->currencyCode = $currencyCode;
        $exchangeHistory->rate = $rate;
        $exchangeHistory->save();
    }


}
