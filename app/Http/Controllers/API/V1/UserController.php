<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Notification;
use App\Models\User;
use App\Models\AdminPromo;
use App\Services\ActivityLogger;
use App\Services\GeneralService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\ShippingAddress;
use Illuminate\Support\Facades\Auth;

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


    public function checkDiscountCode(Request $request)
    {
        $discount_code = $request->discount_code;
        $currentDate = Carbon::now();

        $coupon = AdminPromo::where('promo_code', $discount_code)->first();

        if (!$coupon) {
            return response()->json(['message' => 'failed: Promo code does not exist'], 404);
        }

        if ($coupon->valid_from > $currentDate) {
            return response()->json(['message' => 'failed: Promo code not yet available'], 400);
        }

        if ($coupon->valid_until < $currentDate) {
            return response()->json(['message' => 'failed: Promo code expired'], 400);
        }

        if ($coupon->limited && $coupon->used_count >= $coupon->max_uses) {
            return response()->json(['message' => 'failed: Maximum usage reached for this promo code'], 400);
        }

        //check if user has used promo code using pivot table
        $user_id = auth()->user()->id;
        $user = User::find($user_id);
        // $used_promo = $user->promos()->where('promo_id', $coupon->id)->first();
        // if ($used_promo) {
        //     return response()->json(['message' => 'failed: Promo code already used'], 400);
        // }
        $hasUsedPromo = $request->user()->promos()->where('promo_id', $coupon->id)->exists();

        if ($hasUsedPromo) {
            return response()->json(['message' => 'failed: You have already used this promo code'], 400);
        }


        // if ($request->has('product_type')) {
        //     $eligible_product_type = in_array($request->product_type, $coupon->product_type);
        //     if (!$eligible_product_type) {
        //         return response()->json(['message' => 'failed: Promo code cannot be used for ' . $request->product_type], 400);
        //     }
        // }

        return response()->json(['message' => 'success: Promo code is valid'], 200);
    }



    public function address(Request $request)
    {
        $rules = [
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
        ];

        // If the user already has an address, make all fields optional
        $address = ShippingAddress::where('user_id', Auth::id())->first();
        if ($address) {
            foreach ($rules as $key => $rule) {
                $rules[$key] = 'sometimes|' . $rule;
            }
        }

        $validated = $request->validate($rules);

        if ($address) {
            // Update existing address
            $address->update($validated);
            $message = 'Shipping address updated successfully';
        } else {
            // Create new address
            $validated['user_id'] = Auth::id();
            $address = ShippingAddress::create($validated);
            $message = 'Shipping address created successfully';
        }

        return response()->json(['message' => $message, 'data' => $address], $address ? 200 : 201);
    }




    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'address_line1' => 'required|string|max:255',
    //         'address_line2' => 'nullable|string|max:255',
    //         'city' => 'required|string|max:100',
    //         'state' => 'required|string|max:100',
    //         'postal_code' => 'required|string|max:20',
    //         'country' => 'required|string|max:100',
    //     ]);

    //     $validated['user_id'] = Auth::id();

    //     $address = ShippingAddress::create($validated);

    //     return response()->json(['message' => 'Shipping address created successfully', 'data' => $address], 201);
    // }

    // public function update(Request $request, $id)
    // {
    //     $address = ShippingAddress::where('user_id', Auth::id())->findOrFail($id);

    //     $validated = $request->validate([
    //         'address_line1' => 'sometimes|required|string|max:255',
    //         'address_line2' => 'nullable|string|max:255',
    //         'city' => 'sometimes|required|string|max:100',
    //         'state' => 'sometimes|required|string|max:100',
    //         'postal_code' => 'sometimes|required|string|max:20',
    //         'country' => 'sometimes|required|string|max:100',
    //     ]);

    //     $address->update($validated);

    //     return response()->json(['message' => 'Shipping address updated successfully', 'data' => $address], 200);
    // }



}

