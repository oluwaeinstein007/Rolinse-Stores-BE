<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\ExchangeHistory;
use App\Models\ExchangeRate;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:update-exchange-rates')->daily();
// Schedule::command('app:update-exchange-rates')->everyMinute();

class UpdateExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-exchange-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
            $currencies = ['NGN', 'KES', 'GBP', 'GHS', 'XOF', 'XAF', 'USD'];
            $fromCode = 'USD';

            foreach ($currencies as $currencyCode) {
                $rate = self::fetchExchangeRate($currencyCode, $fromCode);
                self::addNewCurrency($currencyCode, $rate);
            }
    }

    public static function roundNum($num) {
        return number_format($num, 2, '.', '');
    }

    public static function fetchExchangeRate($toCode, $fromCode) {
        $client = new Client();
        $url = 'https://api.apilayer.com/fixer/convert';
        $apikey = env('FOREX_API_KEY');

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
        return self::roundNum($rate);
    }

    public static function addNewCurrency($currencyCode, $rate) {
        $exchangeHistory = new ExchangeHistory();
        // $exchangeHistory->user_id = 1;
        $exchangeHistory->currencyCode = $currencyCode;
        $exchangeHistory->rate = $rate;
        $exchangeHistory->save();

        ExchangeRate::updateOrCreate(
            [
                'currencyCode' => $currencyCode,
            ],
            [
                // 'user_id' => 1,
                'rate' => $rate,
            ]
        );
    }
}
