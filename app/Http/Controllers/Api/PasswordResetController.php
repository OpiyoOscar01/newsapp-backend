<?php
// app/Http/Controllers/Api/PasswordResetController.php

namespace App\Http\Controllers\Api;

use App\Events\PasswordResetRequested;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as RulesPassword;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link to user's email
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLink(Request $request)
    {
        // Validate email
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find user by email
        $user = User::where('email', $request->email)->first();

        // For security, always return success even if user doesn't exist
        // This prevents email enumeration attacks
        if (!$user) {
            Log::info('Password reset requested for non-existent email', ['email' => $request->email]);
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with this email, you will receive a password reset link.'
            ], Response::HTTP_OK);
        }

        // Generate reset token
        $token = $user->generatePasswordResetToken(60); // 60 minutes expiry

        // Dispatch event to send email
        event(new PasswordResetRequested($user, $token, 'email'));

        Log::info('Password reset link sent', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset link has been sent to your email address.'
        ], Response::HTTP_OK);
    }

    /**
     * Verify reset token and return user info
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate token
        if (!$user->validatePasswordResetToken($request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token. Please request a new password reset.'
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'data' => [
                'email' => $user->email,
                'token' => $request->token
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Reset password using token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', RulesPassword::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate token
        if (!$user->validatePasswordResetToken($request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token. Please request a new password reset.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->recordPasswordChange();
        
        // Clear reset token
        $user->clearPasswordResetToken();
        
        // Revoke all existing tokens (force logout from all devices)
        $user->tokens()->delete();

        Log::info('Password reset successful', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully. Please login with your new password.'
        ], Response::HTTP_OK);
    }
}