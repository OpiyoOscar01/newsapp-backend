<?php
// app/Listeners/Auth/SendPasswordResetNotification.php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\PasswordResetRequested;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * Listens for PasswordResetRequested and dispatches the password reset email.
 *
 * Implements ShouldHandleEventsAfterCommit so the notification is
 * only sent once the DB transaction that fired the event has committed.
 */
class SendPasswordResetNotification implements ShouldHandleEventsAfterCommit
{
    private const TOKEN_EXPIRY_MINUTES = 60;
    
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(PasswordResetRequested $event): void
    {
        $user = $event->user;
        $token = $event->token;
        
        $messageData = $this->buildPasswordResetMessage($user, $token);
        
        $this->notificationService->sendToUser(
            user: $user,
            title: $messageData['title'],
            body: $messageData['body'],
            type: 'password_reset',
            channel: $event->channel
        );
    }
    
    /**
     * Build the password reset email message
     */
    private function buildPasswordResetMessage(User $user, string $token): array
    {
        $firstName = $user->first_name ?? $user->name ?? 'Valued Customer';
        $expiryMinutes = self::TOKEN_EXPIRY_MINUTES;
        $appName = config('app.name', 'DefinePress');
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $resetLink = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        
        $title = 'Reset Your ' . $appName . ' Password';
        
        $body = $this->renderEmailBody($firstName, $resetLink, $expiryMinutes, $appName);
        
        return ['title' => $title, 'body' => $body];
    }
    
    /**
     * Render the email HTML body
     */
    private function renderEmailBody(string $firstName, string $resetLink, int $expiryMinutes, string $appName): string
    {
        return "
        <div style='font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;'>
            <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

            <p style='margin: 0 0 16px 0;'>
                We received a request to reset the password for your <strong>{$appName}</strong> account.
                Click the button below to create a new password. This link will expire in <strong>{$expiryMinutes} minutes</strong>.
            </p>

            <div style='
                background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
                border: 1px solid #bfdbfe;
                border-radius: 12px;
                padding: 28px 24px;
                text-align: center;
                margin: 28px 0;
            '>
                <p style='
                    margin: 0 0 6px 0;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 2px;
                    text-transform: uppercase;
                    color: #2563eb;
                '>Reset Your Password</p>

                <a href='{$resetLink}'
                   style='
                       display: inline-block;
                       background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
                       color: #ffffff;
                       text-decoration: none;
                       padding: 14px 40px;
                       border-radius: 8px;
                       font-weight: 700;
                       font-size: 15px;
                       letter-spacing: 0.5px;
                       margin: 20px 0;
                       box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
                   '>
                    Reset My Password →
                </a>

                <p style='
                    margin: 20px 0 0 0;
                    font-size: 12px;
                    color: #6b7280;
                    word-break: break-all;
                '>
                    Or copy this link: <br>
                    <span style='color: #3b82f6;'>{$resetLink}</span>
                </p>
            </div>

            <div style='
                background-color: #fef2f2;
                border-left: 4px solid #ef4444;
                padding: 16px 20px;
                margin: 24px 0;
                border-radius: 8px;
            '>
                <p style='margin: 0; font-size: 14px; color: #374151; line-height: 1.6;'>
                    <strong style='color: #dc2626;'>🔒 Security Note:</strong><br>
                    If you did not request a password reset, please ignore this email. 
                    Your password will remain unchanged. If you believe someone else requested this,
                    please contact our support team immediately.
                </p>
            </div>

            <div style='margin-top: 36px; padding-top: 24px; border-top: 1px solid #e5e7eb;'>
                <p style='margin: 0 0 4px 0; font-size: 15px; color: #374151;'>Warm regards,</p>
                <p style='margin: 0 0 2px 0; font-size: 15px; font-weight: 700; color: #111827;'>{$appName} Security Team</p>
            </div>
        </div>";
    }
}