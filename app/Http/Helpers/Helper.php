<?php

namespace App\Http\Helpers;

use App\Models\ActivityLog;
use App\Models\ExchangeHistory;
use App\Models\ExchangeRate;
use App\Models\Notification;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class Helper{




    // public static function runTopVisaDestination(){
    //      // Get all country codes from VisaCountry
    //      $destinations = VisaCountry::pluck('country_code');

    //      // Get the top 10 booked countries
    //      $bookings = VisaBooked::select('country_code', DB::raw('COUNT(*) as count'))
    //          ->whereIn('country_code', $destinations)
    //          ->groupBy('country_code')
    //          ->orderByDesc('count')
    //          ->take(10)
    //          ->get();

    //      // Fetch VisaCountry details for the top booked countries
    //      $visaCountries = VisaCountry::whereIn('country_code', $bookings->pluck('country_code'))
    //          ->get();

    //      // Add booking count to each country
    //      foreach ($visaCountries as $country) {
    //          $booking = $bookings->where('country_code', $country->country_code)->first();
    //          $country->booking_count = $booking ? $booking->count : 0;
    //      }

    //      //get the cheapest visa type of visa country
    //      foreach ($visaCountries as $country) {
    //          $visaType = VisaType::where('country_code', $country->country_code)
    //              ->select('*', DB::raw('processing_fee + government_fee as total_fee'))
    //              ->orderBy('total_fee', 'asc')
    //              ->first();
    //          $country->cheapest_visa_type = $visaType;
    //          $country->price_from = $visaType->total_fee;
    //          $country->base_code = $visaType->base_code;
    //         // $country->price_from = $visaType->total_fee;
    //         $country->price_from = $visaType->total_price;
    //      }

    //          // Clear the existing entries in the TopVisaDestination table
    //          TopVisaDestination::truncate();

    //      // Update or create entries in TopVisaDestination table
    //      foreach ($visaCountries as $country) {
    //          TopVisaDestination::updateOrCreate(
    //              ['country_code' => $country->country_code],
    //              [
    //                  'count' => $country->booking_count,
    //                  'name' => $country->name,
    //                  'image' => $country->image,
    //                  'price_from' => $country->price_from,
    //                  'base_code' => $country->base_code,
    //              ]
    //          );
    //      }
    // }



    // public static function runConvertPointsToTicket() {
    //     // Fetch users who have accumulated at least 200 points
    //     $users = User::where('tva_point', '>=', 200)->get();

    //     foreach ($users as $user) {
    //         // Convert points to ticket
    //         $ticketValue = $user->tva_point * 0.1; // Assuming 1 TVA point = $0.1
    //         $ticket = new Ticket([
    //             'value' => $ticketValue,
    //             'ticket_code' => uniqid(), // Generate a unique ticket code
    //             'valid_from' => now(),
    //             'valid_until' => now()->addMonths(6), // Valid for 3 months
    //             'user_uuid' => $user->user_uuid,
    //         ]);
    //         $ticket->save();

    //         // Update user's TVA points
    //         $user->tva_point = 0; // Reset points after conversion
    //         $user->save();
    //     }
    // }


    public static function runExchangeRate() {
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
        $exchangeHistory->admin_id = auth()->user()->id ?? 1;
        $exchangeHistory->currencyCode = $currencyCode;
        $exchangeHistory->rate = $rate;
        $exchangeHistory->save();

        ExchangeRate::updateOrCreate(
            [
                'currencyCode' => $currencyCode,
            ],
            [
                'admin_id' => auth()->user()->id ?? 1,
                'rate' => $rate,
            ]
        );
    }


    //function to delete old data
    public static function deleteOldData(){
        self::deleteOldExchangeRateHistory();
        self::deleteOldNotification();
        self::deleteOldActivityLog();
    }


    //delete older than 1 year exchange rate history
    public static function deleteOldExchangeRateHistory(){
        $oldExchangeRateHistory = ExchangeHistory::where('created_at', '<=', now()->subYear())
            ->get();

        foreach ($oldExchangeRateHistory as $history) {
            $history->delete();
        }
    }

    //delete older than 1 year notifcation
    public static function deleteOldNotification(){
        $oldNotifications = Notification::where('created_at', '<=', now()->subYear())
            ->get();

        foreach ($oldNotifications as $notification) {
            $notification->delete();
        }
    }

    //delete activity log older than 2 year
    public static function deleteOldActivityLog(){
        $oldActivityLogs = ActivityLog::where('created_at', '<=', now()->subYears(2))
            ->get();

        foreach ($oldActivityLogs as $activityLog) {
            $activityLog->delete();
        }
    }



}
