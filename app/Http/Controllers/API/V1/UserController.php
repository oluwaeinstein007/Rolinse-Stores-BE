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


    //user
    public function getUser(){
        $user = auth()->user();

        return response()->json([
            'message' => 'User details',
            'data' => $user
        ]);
    }


}

