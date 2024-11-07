<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Notification;
use App\Models\User;
use App\Models\Level;
use App\Models\Community;
use App\Services\ActivityLogger;
use App\Services\GeneralService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    protected $generalService;
    public function __construct(GeneralService $generalService)
    {
        $this->generalService = $generalService;
        // $this->middleware('auth');
    }


    public function editProfile(Request $request)
    {
        $user = auth()->user();
        $user_id = $user->id;

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|required|string',
            'username' => 'sometimes|required|string',
            'gender' => 'sometimes|required|string',
            'date_of_birth' => 'sometimes|required|string',
            'address' => 'sometimes|required|string',
            'country' => 'sometimes|required|string',
            'state' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($user_id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'errors' => 'User not found',
            ], 404);
        }

        $user->update($request->only(['full_name','gender', 'date_of_birth','country', 'state', 'username']));

        return response()->json([
            'message' => 'success',
            'data' => $user,
        ], 200);
    }


    public function getNotification(Request $request){
        $user = auth()->user();
        $perPage = $request->input('perPage', 100);
        $page = $request->input('page', 1);
        $notifications = Notification::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
        $notifications = $notifications->forPage($page, $perPage);

        return response()->json([
            'message' => 'success',
            'data' => $notifications,
        ], 200);
    }

    //change notification status
    public function changeNotificationStatus($id, Request $request){
        $user = auth()->user();
        $notification = Notification::where('user_id', $user->id)->where('id', $id)->first();
        if(!$notification){
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }
        $notification->status = $request->status;
        $notification->save();
        return response()->json([
            'message' => 'Notification status changed successfully',
        ], 200);
    }


    public function getLevels($id = null){
        if ($id) {
            $level = Level::find($id);

            if (!$level) {
                return response()->json(['message' => 'Level not found'], 404);
            }

            return response()->json($level, 200);
        }

        //get all levels except one in the user status
        $user = auth()->user();
        $level = Level::where('id', '!=', $user->level_id)->get();
        // $level = Level::all();

        return response()->json($level, 200);
    }


    public function getCommunity($id = null){
        if ($id) {
            $community = Community::with('rules')->findOrFail($id);

            if (!$community) {
                return response()->json(['message' => 'Level not found'], 404);
            }

            return response()->json($community, 200);
        }

        return response()->json(Community::with('rules')->get(), 200);
    }



    // Store user with bank details
    public function createBankDetails(Request $request)
    {
        $validatedData = $request->validate([
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:11',
            'bank_country_code' => 'nullable|string|max:3',
        ]);

        $country_id = Country::where('alpha_3_code', $validatedData['bank_country_code'])->first()->id;
        $validatedData['bank_country_id'] = $country_id;
        $user = auth()->user();
        $user->update($validatedData);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }


    public function showBankDetails($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }


    public function updateBankDetails(Request $request, $id)
    {
        $validatedData = $request->validate([
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:11',
            'bank_country_code' => 'nullable|string|max:3',
        ]);

        $country_id = Country::where('alpha_3_code', $validatedData['bank_country_code'])->first()->id;
        $validatedData['bank_country_id'] = $country_id;
        $user = User::findOrFail($id);
        $user->update($validatedData);

        return response()->json([
            'message' => 'User bank details updated successfully',
            'data' => $user
        ]);
    }


    public function deleteBankDetails($id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'bank_name' => null,
            'bank_account_name' => null,
            'bank_account_number' => null,
            'bank_country_id' => null,
        ]);

        return response()->json([
            'message' => 'User deleted successfully',
            'data' => $user
        ]);
    }


    //user
    public function getUser(){
        $user = auth()->user();

        //get level name and attach to user
        $level = Level::find($user->level_id);
        $user->level_name = $level->name ?? 'No Level Yet';

        return response()->json([
            'message' => 'User details',
            'data' => $user
        ]);
    }


}

