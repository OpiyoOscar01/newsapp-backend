<?php
// app/Events/PasswordResetRequested.php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user requests a password reset.
 * Listeners are responsible for sending the password reset email.
 */
class PasswordResetRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param User   $user    The user requesting password reset
     * @param string $token   The raw (un-hashed) reset token for the reset link
     * @param string $channel Delivery channel: 'email' | 'sms' | 'both'
     */
    public function __construct(
        public readonly User   $user,
        public readonly string $token,
        public readonly string $channel = 'email'
    ) {}
}