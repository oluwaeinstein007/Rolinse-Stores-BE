<?php

namespace App\Http\Controllers\API\V1;

use App\Exports\TransactionsExport;
use Carbon\Carbon;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $transactions = Transaction::query()
        ->whereHas('user', fn ($query) => $query->where('id', auth()->id()))
        ->with(
            'user:id,first_name,last_name',
            )
        ->get();

        return $this->success('Transactions fetch successfully', $transactions);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Transaction $transaction)
    {
        $transaction->load(
            'user:id,first_name,last_name,email,user_type',
            'receiver:id,first_name,last_name,user_type',
        );
        return $this->success('Transaction fetch successfully', $transaction);
    }

    /**
     * Update the specified resource in storage.
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Transaction $transaction
     * @return \Illuminate\Http\JsonResponse
     */
    // public function update(Request $request, Transaction $transaction)
    // {
    //     if ($request->action == 'process') {
    //         $transaction->status = TransactionStatus::COMPLETED;
    //         $transaction->save();

    //     } elseif ($request->action == 'cancel') {
    //         $transaction->status = TransactionStatus::CANCELLED;
    //         $transaction->save();
    //         // TO-DO reverse the transaction amount back to the user's wallet
    //     }else{
    //         return $this->failure('Unable to update transaction. Invalid request.');
    //     }

    //     return $this->success('Transaction updated successfully');
    // }

    /**
     * Get date range.
     * @param mixed $period
     * @return array
     */
    // private function getDateRange($period): array
    // {
    //     $now = Carbon::now();

    //     //Custom date
    //     if (is_array($period) && isset($period['start']) && isset($period['end'])) {
    //         $start = Carbon::parse($period['start'])->startOfDay();
    //         $end = Carbon::parse($period['end'])->endOfDay();
    //         return compact('start', 'end');
    //     }

    //     $start = match ($period) {
    //         'today' => (clone $now)->startOfDay(),
    //         'last_week' => (clone $now)->startOfWeek()->subWeek(),
    //         'last_month' => (clone $now)->subMonth()->startOfMonth(),
    //         'last_year' => (clone $now)->startOfYear()->subYear(),
    //         'all_time' => Carbon::createFromDate(1970, 1, 1)
    //     };

    //     $end = match ($period) {
    //         'today' => (clone $now)->endOfDay(),
    //         'last_week' => (clone $now)->endOfWeek()->subWeek(),
    //         'this_month' => (clone $now)->endOfMonth(),
    //         'last_year' => (clone $now)->endOfYear(),
    //         'all_time' => $now,
    //     };

    //     return ['start' => $start, 'end' => $end];
    // }

    /**
     * Export transactions to CSV/Excel.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    // public function export()
    // {
    //     return Excel::download(new TransactionsExport, 'transactions.xlsx');
    // }

}
