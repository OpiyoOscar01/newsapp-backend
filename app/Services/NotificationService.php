<?php
// app/Services/Notification/NotificationService.php

declare(strict_types=1);

namespace App\Services;

use App\Mail\StandardEmail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    private const BROADCAST_CHUNK_SIZE = 100;

    public function sendToUser(
        User   $user,
        string $title,
        string $body,
        string $type,
        string $channel = 'email'
    ): void {
        if (in_array($channel, ['in_app', 'both'], true)) {
            $this->persistNotification($user->id, $title, $body, $type, $channel);
        }

        if (in_array($channel, ['email', 'both'], true)) {
            $this->dispatchEmail($user, $title, $body);
        }
    }

    public function broadcastToAll(
        string $title,
        string $body,
        string $type,
        string $channel = 'in_app'
    ): void {
        if (in_array($channel, ['in_app', 'both'], true)) {
            $this->persistSystemNotification($title, $body, $type, $channel);
        }

        if (in_array($channel, ['email', 'both'], true)) {
            User::query()
                ->whereNotNull('email')
                ->chunk(self::BROADCAST_CHUNK_SIZE, function ($users) use ($title, $body) {
                    foreach ($users as $user) {
                        $this->dispatchEmail($user, $title, $body);
                    }
                });
        }
    }

    public function sendNotification(
        string  $title,
        string  $body,
        string  $type,
        string  $channel,
        ?int    $userId = null
    ): void {
        if ($userId !== null) {
            $user = User::find($userId);

            if (!$user) {
                Log::warning('sendNotification: user not found', ['user_id' => $userId]);
                return;
            }

            $this->sendToUser($user, $title, $body, $type, $channel);
            return;
        }

        $this->broadcastToAll($title, $body, $type, $channel);
    }

    private function persistNotification(
        int    $userId,
        string $title,
        string $body,
        string $type,
        string $channel
    ): void {
        Notification::create([
            'user_id'     => $userId,
            'title'       => $title,
            'message'     => $body,
            'target_type' => $type,
            'channel'     => $channel,
            'sent_at'     => Carbon::now(),
        ]);
    }

    private function persistSystemNotification(
        string $title,
        string $body,
        string $type,
        string $channel
    ): void {
        Notification::create([
            'user_id'     => null,
            'title'       => $title,
            'message'     => $body,
            'target_type' => $type,
            'channel'     => $channel,
            'sent_at'     => Carbon::now(),
        ]);
    }

    /**
     * Send email to user - updated to handle regular email field
     */
    private function dispatchEmail(User $user, string $title, string $body): void
    {
        $email = $user->email ?? null;
        
        // Fallback to decrypted email_encrypted if needed
        if (empty($email) && !empty($user->email)) {
            try {
                $email = decrypt($user->email);
            } catch (\Exception $e) {
                Log::error('Failed to decrypt email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                return;
            }
        }
        
        if (empty($email)) {
            Log::warning('dispatchEmail: user has no email address', ['user_id' => $user->id]);
            return;
        }

        try {
            Mail::to($email)->send(new StandardEmail(
                title:    $title,
                mailBody: $body,
                isHtml:   true
            ));

            Log::info('Email dispatched successfully', [
                'user_id' => $user->id,
                'email' => $email
            ]);

        } catch (\Exception $e) {
            Log::error('Email dispatch failed', [
                'user_id' => $user->id,
                'email' => $email,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}