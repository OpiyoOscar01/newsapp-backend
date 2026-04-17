<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewsletterConfirmation;

class NewsletterController extends Controller
{
    use ApiResponseTrait;

    /**
     * Subscribe to newsletter
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'preferences' => 'nullable|array',
            'preferences.categories' => 'nullable|array',
            'preferences.frequency' => 'nullable|in:daily,weekly,monthly',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $validator->errors()
            );
        }

        try {
            // Check if already subscribed
            $existing = NewsletterSubscriber::where('email', $request->email)->first();
            
            if ($existing) {
                if ($existing->status === 'active') {
                    return $this->errorResponse(
                        'This email is already subscribed to our newsletter',
                        409
                    );
                }
                
                // Reactivate unsubscribed user
                $existing->update([
                    'status' => 'active',
                    'unsubscribed_at' => null,
                    'preferences' => $request->preferences,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                $subscriber = $existing;
            } else {
                // Create new subscriber
                $subscriber = NewsletterSubscriber::create([
                    'email' => $request->email,
                    'name' => $request->name,
                    'preferences' => $request->preferences,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'subscribed_at' => now(),
                ]);
            }

            // Send confirmation email (optional)
            // Mail::to($subscriber->email)->send(new NewsletterConfirmation($subscriber));

            return $this->successResponse([
                'subscriber' => [
                    'email' => $subscriber->email,
                    'name' => $subscriber->name,
                    'status' => $subscriber->status,
                ],
                'message' => 'Successfully subscribed to newsletter'
            ], 'Subscription successful');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to subscribe',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Unsubscribe from newsletter
     */
    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:newsletter_subscribers,email',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $validator->errors()
            );
        }

        try {
            $subscriber = NewsletterSubscriber::where('email', $request->email)->first();
            $subscriber->unsubscribe();

            return $this->successResponse(
                null,
                'Successfully unsubscribed from newsletter'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to unsubscribe',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get subscriber preferences
     */
    public function getPreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:newsletter_subscribers,email',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $validator->errors()
            );
        }

        try {
            $subscriber = NewsletterSubscriber::where('email', $request->email)->first();
            
            return $this->successResponse([
                'email' => $subscriber->email,
                'name' => $subscriber->name,
                'preferences' => $subscriber->preferences,
                'status' => $subscriber->status,
                'subscribed_at' => $subscriber->subscribed_at,
            ], 'Preferences retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve preferences',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Update subscriber preferences
     */
    public function updatePreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:newsletter_subscribers,email',
            'name' => 'nullable|string|max:255',
            'preferences' => 'nullable|array',
            'preferences.categories' => 'nullable|array',
            'preferences.frequency' => 'nullable|in:daily,weekly,monthly',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $validator->errors()
            );
        }

        try {
            $subscriber = NewsletterSubscriber::where('email', $request->email)->first();
            
            $updateData = [];
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('preferences')) {
                $updateData['preferences'] = array_merge(
                    $subscriber->preferences ?? [],
                    $request->preferences
                );
            }
            
            $subscriber->update($updateData);

            return $this->successResponse([
                'email' => $subscriber->email,
                'name' => $subscriber->name,
                'preferences' => $subscriber->preferences,
            ], 'Preferences updated');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update preferences',
                500,
                $e->getMessage()
            );
        }
    }
}