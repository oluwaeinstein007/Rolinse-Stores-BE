<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\User;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Community;
use App\Models\Setting;
use App\Models\CommunityRule;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use App\Services\GeneralService;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected $generalService;
    protected $notificationService;

    public function __construct(GeneralService $generalService, NotificationService $notificationService)
    {
        $this->generalService = $generalService;
        $this->notificationService = $notificationService;
        // $this->middleware('auth');
    }

    //User Management
    public function getUsers(Request $request, $id = null)
    {
        if ($id) {
            $user = User::where('id', $id)->first();
            if (!$user) {
                return $this->failure('User not found', null, 404);
            }
            return $this->success('User retrieved successfully', $user, [], 200);
        }

        $validator = Validator::make($request->query(), [
            'perPage' => 'sometimes|integer',
            'page' => 'sometimes|integer',
            'user_role' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $query = User::query();
        // $query = User::withCount(['posts', 'likes', 'comments']);
        if ($request->input('user_role')) {
            $query->where('user_role_id', $request->input('user_role')); // Filter users based on user_role
        }

        $perPage = $request->input('perPage', 100);
        $users = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        if ($users->isEmpty()) {
            return $this->failure('No users found', null, 404);
        }
        $user_count = $query->count();
        $total_users = User::count();
        $data = [
            'count' => $user_count,
            'total_users' => $total_users,
            'users' => $users,
        ];

        return $this->success('Users retrieved successfully', $data, [], 200);
    }


    public function getUserMetaData()
    {
        $users = User::all();
        $totalUsers = $users->count();
        $activeUsers = $users->where('is_active', true)->count();
        $verifiedUsers = $users->where('email_verified_at', '!=', null)->count();
        $inactiveUsers = $users->where('is_active', false)->count();
        $deletedUsers = User::onlyTrashed()->count();

        $data = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'verified_users' => $verifiedUsers,
            'inactive_users' => $inactiveUsers,
            'deleted_users' => $deletedUsers,
        ];

        return $this->success('User metadata retrieved successfully', $data, [], 200);
    }


    public function suspendUser(Request $request, $id)
    {
        $request->validate([
            'suspend' => 'required|boolean',
            'reason' => 'nullable|string',
            'duration' => 'nullable|string',
        ]);

        $superadmin = auth()->user();

        // If the current admin is the same as the one being suspended, return an error
        if ($superadmin->id == $id) {
            return $this->failure('You cannot suspend your own account. Please contact another admin.', null, 403);
        }

        $user = User::find($id);
        if (!$user) {
            return $this->failure('User not found', null, 404);
        }
        $user->update(['is_suspended' => $request->suspend, 'status' => $request->suspend ? 'suspended' : 'active', 'suspension_reason' => $request->reason ?? '', 'suspension_duration' => $request->duration ?? '', 'suspension_date' => $request->suspend ? Carbon::now() : null]);

        if($request->suspend){
            $this->notificationService->userNotification($user, 'Account', 'Suspended', 'Account Suspended', 'Your account has been suspended. Reason: '.$request->reason.' Duration: '.$request->duration, true);
            ActivityLogger::log('User', 'Account Suspended', 'User has been suspended. Reason: '.$request->reason.' Duration: '.$request->duration, $user->id);
        }else{
            $this->notificationService->userNotification($user, 'Account', 'Unsuspended', 'Account Unsuspended', 'Your account has been unsuspended.', true);
            ActivityLogger::log('User', 'Account Unsuspended', 'User has been unsuspended.', $user->id);
        }

        $message = $user->is_suspended ? 'Account suspended successfully.' : 'Account unsuspended successfully.';

        return $this->success($message, $user, [], 200);
    }

    // Delete user
    public function deleteUser($id)
    {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return $this->failure('User not found', null, 404);
        }
        $user->delete();
        return $this->success('User deleted successfully', [], 200);
    }

    //restore user account
    public function restoreUser($id)
    {
        $user = User::withTrashed()->where('id', $id)->first();
        if (!$user) {
            return $this->failure('User not found', null, 404);
        }
        $user->restore();
        return $this->success('User account restored successfully', [], 200);
    }


    // Get user activities
    public function getUserActivities($id = null)
    {
        $userActivitiesQuery = ActivityLog::when($id, function ($query) use ($id) {
            return $query->where('user_id', $id);
        })
        ->with('user:id,first_name,last_name,email,country');

        $activityCount = $userActivitiesQuery->count();
        $userActivities = $userActivitiesQuery->paginate(10);

        $data = [
            'count' => $activityCount,
            'activities' => $userActivities,
        ];

        return $this->success('User Activities retrieved successfully', $data, [], 200);
    }


    public function adminGetProduct(Request $request, $id = null)
    {
        $query = Product::query();

        if ($id) {
            $product = $query->find($id);

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            return response()->json($product, 200);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('visibility')) {
            $query->where('visibility', $request->visibility);
        }

        $products = $query->get();
        return response()->json(['message' => 'Product filter successfully', 'data' => $products], 200);
    }


    public function getTransactions(Request $request) {
        $transactionId = $request->input('transactionId');
        $userId = $request->input('userId');
        $userEmail = $request->input('userEmail');
        $type = $request->input('type');

        if ($transactionId) {
            $transaction = Transaction::where('transaction_id', $transactionId)
                ->with([
                    'sender:id,full_name,email,phone_number,whatsapp_number',
                    'receiver:id,full_name,email,bank_name,bank_account_name,bank_account_number,phone_number,whatsapp_number'
                ])->first();

            return $transaction
                ? response()->json(['message' => 'Transaction fetched successfully.', 'data' => $transaction], 200)
                : response()->json(['message' => 'Transaction not found.'], 404);
        }

        if ($userEmail) {
            $user = User::where('email', $userEmail)->first();
            $userId = $user->id ?? null;
        }

        if ($userId) {
            $sendOrReceive = $type == 'incoming' ? 'sender_user_id' : 'receiver_user_id';
            $transactions = Transaction::where($sendOrReceive, $userId)
                ->with([
                    'sender:id,full_name,email,phone_number,whatsapp_number',
                    'receiver:id,full_name,email,bank_name,bank_account_name,bank_account_number,phone_number,whatsapp_number'
                ])->get();

            return $transactions->isNotEmpty()
                ? response()->json(['message' => 'Transactions fetched successfully.', 'data' => $transactions], 200)
                : response()->json(['message' => 'No transactions found for this user.'], 404);
        }

        $transactions = Transaction::with([
                'sender:id,full_name,email,phone_number,whatsapp_number',
                    'receiver:id,full_name,email,bank_name,bank_account_name,bank_account_number,phone_number,whatsapp_number'
            ])->get();

        return $transactions->isNotEmpty()
            ? response()->json(['message' => 'Transactions fetched successfully.', 'data' => $transactions], 200)
            : response()->json(['message' => 'No transactions found.'], 404);
    }


    public function changeTransactionStatus(Request $request)
    {
        $request->validate([
            'transactionId' => 'required|string',
            'status' => 'required|string|in:pending,completed,failed',
        ]);
        $transactionId = $request->transactionId;
        $transaction = Transaction::where('transaction_id', $transactionId)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        $transaction->status = $request->status;
        $transaction->save();

        if($transaction->status == 'completed'){
            $user = User::find($transaction->receiver_user_id);
            $receiver = User::find($transaction->sender_user_id);
            $this->generalService->adjustBalance($transaction->receiver_user_id, $transaction->amount);
            $this->generalService->adjustRefSort($transaction->receiver_user_id);
            $this->notificationService->userNotification($receiver, 'Payment', 'Payment received', 'Transaction Complete.', 'You have received a transaction with ID: ' . $transaction->transaction_id. ' from ' . $user['full_name'], false);
            $this->notificationService->userNotification($user, 'Payment', 'Payment sent', 'Transaction Complete.', 'You have sent a transaction with ID: ' . $transaction->transaction_id. ' to ' . $receiver['full_name'], false);
            ActivityLogger::log('Payment', 'Transaction Complete', 'The transaction with ID: ' . $transaction->transaction_id . ' has been completed, initiated by ' . $user['full_name'] . ' and received by ' . $receiver['full_name'], $receiver->id);

            // update user level
            $user->level_id = $transaction->level_id;
            $user->ongoing_transaction = false;
            $user->save();
            $receiver->ongoing_transaction = false;
            $receiver->save();
            $this->notificationService->userNotification($user, 'Level', 'Upranking', 'You are now in next level', 'Congratulations! You have successfully completed a transaction and you are now in the next level.', false);
            ActivityLogger::log('Level', 'Upranking', 'User ' . $user['full_name'] . ' has been upranked to the next level' . Level::find($transaction->level_id)->name, $user->id);
        }

        return response()->json(['message' => 'Transaction status updated successfully.', 'data' => $transaction], 200);
    }

}
