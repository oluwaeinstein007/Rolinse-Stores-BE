<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\GeneralService;

class FinanceController extends Controller
{
    protected $generalService;

    public function __construct(GeneralService $generalService)
    {
        $this->generalService = $generalService;
        // $this->middleware('auth');
    }
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
                    'rate' => ExchangeRate::where('currencyCode', $upperCode)->first()->rate,
                ];
            }
        }

        return response()->json([
            'message' => 'success',
            'data' => $data
        ], 200);
    }


    public function manualGetExchangeRate(Request $request) {
        $validated = $request->validate([
            'currency_code' => 'nullable|string|size:3',
        ]);

        $currencies = ['NGN', 'KES', 'GBP', 'GHS', 'XOF', 'XAF', 'USD'];
        $fromCode = 'USD';

        $file = storage_path('CurrencyCode/currency_code.json');
        $currencyJson = json_decode(file_get_contents($file), true);

        if ($request->has('currency_code')) {
            // check if the provided currency code exists in the JSON file
            if (!isset($currencyJson[strtoupper($validated['currency_code'])])) {
                return response()->json([
                    'message' => 'Invalid currency code provided.',
                ], 400);
            }
            $currencies = [strtoupper($validated['currency_code'])];
        }

        foreach ($currencies as $currencyCode) {
            $exchangeRate = $this->generalService->newCurrency($currencyCode);
            $this->generalService->addNewCurrency($currencyCode,$exchangeRate);
        }

        $rate = ExchangeRate::all();

        return response()->json([
            'message' => 'Exchange rates updated successfully.',
            'data' => $rate
        ], 200);
    }

    // sales report for sales growth

    /**
     * Get sales growth percentage.
     * Compares current month's sales to the previous month's sales.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSalesGrowthSummary()
    {
        $currentMonth = now()->startOfMonth();
        $previousMonth = now()->subMonth()->startOfMonth();

        $currentMonthSales = Order::whereBetween('created_at', [$currentMonth, $currentMonth->endOfMonth()])
            ->where('status', 'completed')
            ->sum('grand_total_ngn');

        $countCurrentMonthSales = Order::whereBetween('created_at', [$currentMonth, $currentMonth->endOfMonth()])
            ->where('status', 'completed')
            ->count();

        $avgCurrentMonthSales = $countCurrentMonthSales > 0 ? $currentMonthSales / $countCurrentMonthSales : 0;

        $previousMonthSales = Order::whereBetween('created_at', [$previousMonth, $previousMonth->endOfMonth()])
            ->where('status', 'completed')
            ->sum('grand_total_ngn');

        $previousMonthSalesCount = Order::whereBetween('created_at', [$previousMonth, $previousMonth->endOfMonth()])
            ->where('status', 'completed')
            ->count();

        $avgPreviousMonthSales = $previousMonthSalesCount > 0 ? $previousMonthSales / $previousMonthSalesCount : 0;



        $growth = 0;
        if ($previousMonthSales > 0) {
            $growth = (($currentMonthSales - $previousMonthSales) / $previousMonthSales) * 100;
            $avgValueGrowth = (($avgCurrentMonthSales - $avgPreviousMonthSales) / $avgPreviousMonthSales) * 100;
        } elseif ($currentMonthSales > 0) {
            $growth = 100; // If previous sales were 0 and current are positive, growth is infinite, represent as 100% increase
        }

        return response()->json([
            'message' => 'success',
            'data' => [
                'current_month_sales' => $currentMonthSales,
                'current_month_sales_count' => $countCurrentMonthSales,
                'avg_current_month_sales_value' => $avgCurrentMonthSales,
                'previous_month_sales' => $previousMonthSales,
                'previous_month_sales_count' => $previousMonthSalesCount,
                'avg_previous_month_sales_value' => $avgPreviousMonthSales,
                'sales_growth_percentage' => round($growth, 2),
            ]
        ], 200);
    }


    /**
     * Get sales graph data (daily and monthly).
     * Includes total sales, value, and value per sale.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function getSalesGraphData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'granularity' => 'sometimes|in:daily,monthly,both',
            'currency' => 'sometimes|string|size:3',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();
        $granularity = $validated['granularity'] ?? 'both';
        $returnCurrency = $validated['currency'] ?? 'USD';

        $data = [];

        // Daily sales data
        if (in_array($granularity, ['daily', 'both'])) {
            $dailySales = Order::selectRaw('DATE(created_at) as date, SUM(grand_total_ngn) as total_sales, AVG(grand_total_ngn) as value_per_sale, COUNT(*) as order_count')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) use ($returnCurrency) {
                    $baseCurrency = OrderItem::where('order_id', $item->id)->first()?->currency ?? 'NGN';

                    return [
                        'date' => $item->date,
                        'total_sales' => $this->generalService->convertMoney(
                            $baseCurrency,
                            (float) $item->total_sales,
                            $returnCurrency
                        ),
                        'value_per_sale' => $this->generalService->convertMoney(
                            $baseCurrency,
                            (float) $item->value_per_sale,
                            $returnCurrency
                        ),
                        'order_count' => (int) $item->order_count,
                        'currency' => $returnCurrency,
                    ];
                });

            $data['daily'] = $dailySales;
        }

        // Monthly sales data
        if (in_array($granularity, ['monthly', 'both'])) {
            $monthlySales = Order::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(grand_total_ngn) as total_sales, AVG(grand_total_ngn) as value_per_sale, COUNT(*) as order_count')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) use ($returnCurrency) {
                    $baseCurrency = OrderItem::where('order_id', $item->id)->first()?->currency ?? 'NGN';

                    return [
                        'month' => $item->month,
                        'total_sales' => $this->generalService->convertMoney(
                            $baseCurrency,
                            (float) $item->total_sales,
                            $returnCurrency
                        ),
                        'value_per_sale' => $this->generalService->convertMoney(
                            $baseCurrency,
                            (float) $item->value_per_sale,
                            $returnCurrency
                        ),
                        'order_count' => (int) $item->order_count,
                        'currency' => $returnCurrency,
                    ];
                });

            $data['monthly'] = $monthlySales;
        }

        // Calculate summary statistics with currency conversion
        $totalSalesRaw = Order::whereBetween('created_at', [$startDate, $endDate])->sum('grand_total');
        $avgOrderValueRaw = Order::whereBetween('created_at', [$startDate, $endDate])->avg('grand_total');

        // Get the base currency from the first order in the range
        $firstOrder = Order::whereBetween('created_at', [$startDate, $endDate])
            ->first();
        $baseCurrency = OrderItem::where('order_id', $firstOrder->id)->first()?->currency ?? 'NGN';

        $summary = [
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'total_sales' => $this->generalService->convertMoney(
                $baseCurrency,
                $totalSalesRaw,
                $returnCurrency
            ),
            'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
            'average_order_value' => $this->generalService->convertMoney(
                $baseCurrency,
                $avgOrderValueRaw,
                $returnCurrency
            ),
            'currency' => $returnCurrency,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Sales data retrieved successfully',
            'data' => $data,
            'summary' => $summary,
        ], 200);
    }


    /** DEPRECATED
     * Get top selling product category pie chart data.
     * This method assumes the existence of a BestSeller model or similar
     * that can provide aggregated sales data by category.
     * If not, a join with OrderItems, Products, and Categories would be needed.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    // public function getTopSellingCategories()
    // {
    //     // Using BestSeller model as it's present in open tabs.
    //     // Adjust query if BestSeller model structure differs or if a join is more appropriate.
    //     $topCategories = \App\Models\BestSeller::with('category') // Assuming BestSeller has a 'category' relationship
    //         ->select('category_id', \DB::raw('SUM(quantity) as total_quantity_sold')) // Assuming BestSeller has quantity and category_id
    //         ->groupBy('category_id')
    //         ->orderByDesc('total_quantity_sold')
    //         ->limit(10) // Get top 10 categories
    //         ->get();

    //     // Format data for pie chart
    //     $chartData = $topCategories->map(function ($item) {
    //         return [
    //             'name' => $item->category->name ?? 'Unknown Category', // Assuming Category model has a 'name' attribute
    //             'value' => $item->total_quantity_sold,
    //         ];
    //     });

    //     return response()->json([
    //         'message' => 'success',
    //         'data' => $chartData
    //     ], 200);
    // }
}
