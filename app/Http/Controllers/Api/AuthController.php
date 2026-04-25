<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rules\Password as RulesPassword;

class AuthController extends Controller
{
    /**
     * Register a new user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', RulesPassword::defaults()],
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create the user
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->name ?? ($request->first_name . ' ' . $request->last_name),
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Check if user email is in admin emails list from .env
        $adminEmails = explode(',', env('ADMIN_EMAILS', ''));
        $isAdmin = in_array($request->email, $adminEmails);
        
        // Assign role - simple approach without specifying guard
        if ($isAdmin) {
            $user->assignRole('admin');
        } else {
            $user->assignRole('user');
        }

        // Create API token for the user
        $token = $user->createToken('auth-token')->plainTextToken;

        // Return success response with user data and token
        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'is_admin' => $isAdmin,
                    'roles' => $user->getRoleNames(),
                    'created_at' => $user->created_at,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Login user and create token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validate login credentials
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find user by email (since Auth::attempt doesn't work with sanctum)
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user is admin
        $isAdmin = $user->hasRole('admin');
        
        // Revoke all existing tokens (optional - for security)
        $user->tokens()->delete();
        
        // Create new API token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'is_admin' => $isAdmin,
                    'roles' => $user->getRoleNames(),
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]
        ], Response::HTTP_OK);
    }

  /**
 * Logout user (revoke token)
 * 
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function logout(Request $request)
{
    try {
        // Get authenticated user via sanctum guard
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated user found',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Delete all tokens for this user (logout from all devices)
        $user->tokens()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ], Response::HTTP_OK);
        
    } catch (\Exception $e) {
        Log::error('Logout error: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to logout',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Get authenticated user profile
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'success' => true,
            'message' => 'User profile retrieved successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Update user profile
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validate update data
        $validator = Validator::make($request->all(), [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update user data
        if ($request->has('first_name')) {
             $request->first_name;
        }
        if ($request->has('last_name')) {
            $request->last_name;
        }
        if ($request->has('name')) {
            $user->name = $request->name;
        } elseif ($request->has('first_name') || $request->has('last_name')) {
            $user->name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Change user password
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validate password change data
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Optional: Revoke all tokens after password change
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Test authentication (for debugging)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testAuth(Request $request)
    {
        return response()->json([
            'auth_check' => Auth::check(),
            'auth_guard_check' => Auth::guard('sanctum')->check(),
            'auth_id' => Auth::id(),
            'auth_guard_id' => Auth::guard('sanctum')->id(),
            'user' => Auth::user(),
            'guard_user' => Auth::guard('sanctum')->user(),
            'bearer_token' => $request->bearerToken(),
            'has_token' => !empty($request->bearerToken()),
            'default_guard' => config('auth.defaults.guard'),
            'sanctum_guard_exists' => array_key_exists('sanctum', config('auth.guards', [])),
        ]);
    }
}