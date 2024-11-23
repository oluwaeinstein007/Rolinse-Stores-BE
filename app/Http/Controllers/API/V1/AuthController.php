<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Mail\OTPMail;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\GeneralService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    //this method adds new users
    /**
     * Create an Account
     */
    protected $generalService;
    protected $notificationService;

    public function __construct(GeneralService $generalService, NotificationService $notificationService)
    {
        $this->generalService = $generalService;
        $this->notificationService = $notificationService;
        // $this->middleware('auth');
    }

    public function generateRefCode()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $RefCode = '';

        for ($i = 0; $i < 6; $i++) {
            $index = rand(0, strlen($chars) - 1);
            $RefCode .= $chars[$index];
        }

        return "Ref" . $RefCode;
    }


    public function handleReferral($referral_id)
    {
        $referral = User::find($referral_id);

        // Increment the referral count of the user
        $referral->referral_count += 1;
        $referral->update();
        //notify the user
        $this->notificationService->userNotification($referral, 'Referral', 'Referral Notification', 'You have a new referral', 'You have a new referral', false, null, 'View Referral');

        // $this->notificationService->userNotification($receiver, 'Payment', 'Payment request', 'You have received a payment request.', 'You have received a payment request from ' . $user['full_name'], false, $link, 'Confirm Payment');
    }


    public function verifyReferralCode(Request $request)
    {
        $referralCode = $request->input('referral_code');

        $userExists = User::where('referral_code', $referralCode)->exists();
        if (!$userExists) {
            return response()->json([
                'message' => 'Referral code does not exist'
            ], 404);
        }

        return response()->json([
            'message' => 'Referral code exists'
        ], 200);
    }


    //create User account
    public function createAccount(Request $request)
    {
        $attr = Validator::make($request->all(), [
            'email' => 'required|string|email|unique:users,email',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'username' => 'nullable|string|unique:users,username',
            'phone_number' => 'nullable|string',
            'country' => 'nullable|string',
            'referral_by' => 'nullable|string|exists:users,referral_code',
        ]);

        // if there are errors with the validation, return the errors
        if ($attr->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $attr->errors()
            ], 422);
        }

        if ($request->referral_by) {
            $referral = User::where('referral_code', $request->referral_by)->first()->id ?? null;
            $this->handleReferral($referral);
        }

        $RefCode = $this->generateRefCode();
        $RefLink = env('FRONTEND_BASE_URL') . '/register?ref=' . $RefCode;

        $username = $request['email'] ? explode('@', $request['email'])[0] . rand(1000, 9999) : null;

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'whatsapp_number' => $request->whatsapp_number,
            'country' => $request->country,
            'referral_code' => $RefCode,
            'referral_link' => $RefLink,
            'referred_by' => $referral ?? null,
            'username' => $username,
            'user_role_id' => 2,
        ]);

        $token = $user->createToken('authToken')->plainTextToken;
        $this->generateOtp($request->email);

        ActivityLogger::log('User', 'User Registration', 'User has successfully registered', $user->id);

        return response()->json([
            'message' => 'success',
            'token' => $token,
            'data' => $user
        ], 201);
    }



    public function checkSuspendServed($user){
        $suspensionDate = Carbon::parse($user->suspension_date);
        $suspensionDuration = $user->suspension_duration;
        $suspensionEndDate = $suspensionDate->addWeeks($suspensionDuration);


        if (now()->gt($suspensionEndDate)) {
            $user->is_suspended = false;
            $user->suspension_reason = null;
            $user->suspension_date = null;
            $user->suspension_duration = null;
            $user->update();

            ActivityLogger::log('User', 'User Suspension Served', 'User suspension has been served', $user->id);

            // return response()->json([
            //     'message' => 'success',
            //     'user' => $user
            // ], 200);
        }

        return response()->json([
            'message' => 'Account is suspended',
            'suspension_end_date' => $suspensionEndDate
        ], 401);
    }


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!auth()->attempt($credentials, $request->remember)) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (!auth()->user()->email_verified_at) {
            return response()->json(['error' => 'Email not verified.'], 401);
        }

        //check if suspended then if so write code to check if suspension has expired
        if (auth()->user()->is_suspended) {
            // return response()->json(['error' => 'Account is suspended.'], 401);
            return $this->checkSuspendServed(auth()->user());
        }

        ActivityLogger::log('User', 'User Login', 'User has successfully logged in', auth()->user()->id);

        return response()->json([
            'token' => auth()->user()->createToken('authToken')->plainTextToken,
            'user' => auth()->user()
        ]);
    }



    public function verifyEmail(Request $request)
    {
        $email = $request->input('email');

        $userExists = User::where('email', $email)->exists();
        if ($userExists) {
            return response()->json([
                'message' => 'Email already exists'
            ], 409); // Conflict status code
        }

        return response()->json([
            'message' => 'Email is available'
        ], 200);
    }

    public function verifyUsername(Request $request)
    {
        $username = $request->input('username');

        $userExists = User::where('username', $username)->exists();
        if ($userExists) {
            return response()->json([
                'message' => 'Username already exists'
            ], 409);
        }

        return response()->json([
            'message' => 'Username is available'
        ], 200);
    }


    public function socialAuth(Request $request)
    {
        // Get user by email
        $user = User::where('email', $request->input('email'))->first();

        //Check if user has an account
        if ($user) {
            $token = $user->createToken('authToken')->plainTextToken;

            return response()->json([
                'message' => 'success',
                'token' => $token,
                'user' => $user
            ], 200);
        }

        //make password
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';

        for ($i = 0; $i < 6; $i++) {
            $index = rand(0, strlen($chars) - 1);
            $password .= $chars[$index];
        }

        $RefCode = $this->generateRefCode();
        $username = $request->username ?? $request['email'] ? explode('@', $request['email'])[0] . rand(1000, 9999) : null;

        $user = User::create([
            'password' => Hash::make($password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
            'country' => $request->country,
            'user_role_id' => 2,
            'referral_code' => $RefCode,
            'is_social' => true,
            'social_type' => $request->social_type,
            'email_verified_at' => now(),
            'username' => $username
        ]);

        // Create token for the new user
        $token = $user->createToken('authToken')->plainTextToken;


        return response()->json([
            'message' => 'success',
            'token' => $token,
            'data' => $user
        ], 200);
    }


    public function logout()
    {
        auth()->user()->tokens()->delete();

        ActivityLogger::log('User', 'User Logout', 'User has successfully logged out', auth()->user()->id);

        return response()->json(['message' => 'success'], 200);
    }


    public function sendOtp(Request $request)
    {
        $email = $request->email;
        $checkUser = User::where('email', $email)->first();
        if ($checkUser) {
            // user exist
            $this->generateOtp($request->email);
            return response()->json(['message' => 'success'], 200);
        } else {
            return response()->json(['message' => 'failed', 'error' => 'User does not exist'], 422);
        }
    }


    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $this->validateOTP($request->email, $request->otp);

            // Clear OTP and expiration timestamp as it is no longer needed
            $user->auth_otp = null;
            $user->auth_otp_expires_at = null;

            // Set email verification timestamp
            if($user->email_verified_at == null){
                $user->email_verified_at = now();
                $user->save();
            }

            $token = $user->createToken('authToken')->plainTextToken;

            return response()->json([
                'message' => 'success',
                'token' => $token,
                // 'data' => $user
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'errors' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    //soft delete user account
    public function deleteAccount(Request $request)
    {
        $user = auth()->user();
        $user->delete();

        ActivityLogger::log('User', 'User Account Deletion', 'User has successfully deleted account', $user->id);

        return response()->json(['message' => 'success'], 200);
    }


    private function generateOtp($email)
    {
        // Generate 6 random digits
        $randomDigits = mt_rand(100000, 999999);

        // Find the user by email
        $user = User::where('email', $email)->first();

        if ($user) {
            // Set the OTP and expiration timestamp
            $user->auth_otp = $randomDigits;
            $user->auth_otp_expires_at = Carbon::now()->addMinutes(5);
            $user->save();
        }

        try {
            // Send the OTP via email
            //   Mail::to($email)->send(new OTPMail($randomDigits));
            //  $user = $user;
            $notification = ['title' => 'OTP Code from Maldorini', 'otp' => $randomDigits];
            $this->notificationService->sendOTPNotification($user, $notification);
        } catch (Exception $e) {
            return ($e);
        }

        return $randomDigits;
    }

    private function validateOTP($email, $otp)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new Exception('Email does not exist', 404);
        }

        if ($user->auth_otp != $otp) {
            throw new Exception('OTP is not correct', 400);
        }

        // Check if OTP has expired
        if ($user->auth_otp_expires_at && now()->gt($user->auth_otp_expires_at)) {
            throw new Exception('OTP has expired', 422);
        }

        return $user;
    }


    public function changePassword(Request $request)
    {
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed'
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'failed', 'error' => $validator->errors()], 400);
        }

        if (!password_verify($request->old_password, $user->password)) {
            return response()->json(['message' => 'failed', 'error' => 'Old password is incorrect'], 400);
        }
        $user->password = Hash::make($request->new_password);
        $user->update();

        ActivityLogger::log('User', 'User Password Change', 'User has successfully changed password', $user->id);

        return response()->json(['message' => 'success'], 200);
    }


    public function updatePassword(Request $request)
    {
        $attr = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed', //confirmed means password_confirmation
        ]);

        if ($attr->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $attr->errors()
            ], 422);
        }

        $user = auth()->user();
        $user->password = Hash::make($request->password);
        $user->update();

        ActivityLogger::log('User', 'User Password Update', 'User has successfully updated password', $user->id);

        return response()->json(['message' => 'success'], 200);
    }
}

