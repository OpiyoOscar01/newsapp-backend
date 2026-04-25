<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'password_reset_token',
        'password_reset_token_expires_at',
        'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'password_reset_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_reset_token_expires_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * The attributes that should be cast (alternative syntax).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password_reset_token_expires_at' => 'datetime',
        'password_changed_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Accessors & Mutators
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }
        
        if ($this->first_name) {
            return $this->first_name;
        }
        
        return $this->name ?? $this->email ?? 'User';
    }

    /**
     * Get the user's display name (alias for full_name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name;
    }

    /**
     * Get the user's first name or fallback to email username.
     */
    public function getFirstNameAttribute($value): ?string
    {
        return $value ?? ($this->name ? explode(' ', $this->name)[0] : null);
    }

    /**
     * Get the user's last name or fallback.
     */
    public function getLastNameAttribute($value): ?string
    {
        return $value ?? ($this->name && strpos($this->name, ' ') !== false ? explode(' ', $this->name)[1] : null);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Password Reset Functionality
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a password reset token for the user.
     *
     * @param int $expiryMinutes Minutes until token expires (default: 60)
     * @return string The raw token to be sent to the user
     */
    public function generatePasswordResetToken(int $expiryMinutes = 60): string
    {
        // Generate a secure random token
        $token = Str::random(64);
        
        // Store hashed token for security
        $this->password_reset_token = Hash::make($token);
        $this->password_reset_token_expires_at = Carbon::now()->addMinutes($expiryMinutes);
        $this->save();
        
        return $token;
    }

    /**
     * Validate a password reset token.
     *
     * @param string $token The raw token to validate
     * @return bool True if token is valid and not expired
     */
    public function validatePasswordResetToken(string $token): bool
    {
        // Check if token exists
        if (!$this->password_reset_token) {
            return false;
        }
        
        // Check if token has expired
        if (!$this->password_reset_token_expires_at || $this->password_reset_token_expires_at->isPast()) {
            return false;
        }
        
        // Verify the token hash
        return Hash::check($token, $this->password_reset_token);
    }

    /**
     * Check if the user has a valid (non-expired) password reset token.
     *
     * @return bool
     */
    public function hasValidPasswordResetToken(): bool
    {
        return $this->password_reset_token !== null 
            && $this->password_reset_token_expires_at !== null
            && !$this->password_reset_token_expires_at->isPast();
    }

    /**
     * Clear the user's password reset token.
     *
     * @return void
     */
    public function clearPasswordResetToken(): void
    {
        $this->password_reset_token = null;
        $this->password_reset_token_expires_at = null;
        $this->save();
    }

    /**
     * Record the timestamp when password was changed.
     *
     * @return void
     */
    public function recordPasswordChange(): void
    {
        $this->password_changed_at = Carbon::now();
        $this->save();
    }

    /**
     * Check if password was changed recently (within given minutes).
     *
     * @param int $minutes
     * @return bool
     */
    public function wasPasswordChangedRecently(int $minutes = 5): bool
    {
        if (!$this->password_changed_at) {
            return false;
        }
        
        return $this->password_changed_at->diffInMinutes(Carbon::now()) <= $minutes;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Security & Authentication Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Determine if the user is an administrator.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Determine if the user has verified their email.
     *
     * @return bool
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark the user's email as verified.
     *
     * @return void
     */
    public function markEmailAsVerified(): void
    {
        if (is_null($this->email_verified_at)) {
            $this->email_verified_at = Carbon::now();
            $this->save();
        }
    }

    /**
     * Revoke all API tokens for the user.
     *
     * @return void
     */
    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    /**
     * Revoke all tokens except the current one.
     *
     * @param string|null $currentTokenId
     * @return void
     */
    public function revokeAllTokensExcept(?string $currentTokenId = null): void
    {
        if ($currentTokenId) {
            $this->tokens()->where('id', '!=', $currentTokenId)->delete();
        } else {
            $this->revokeAllTokens();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query Scopes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Scope a query to only include verified users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope a query to only include unverified users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('email_verified_at');
    }

    /**
     * Scope a query to only include users with active password reset tokens.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithValidResetToken($query)
    {
        return $query->whereNotNull('password_reset_token')
                     ->where('password_reset_token_expires_at', '>', Carbon::now());
    }

    /**
     * Scope a query to only include users who changed password recently.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $minutes
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePasswordChangedWithin($query, int $minutes = 5)
    {
        return $query->where('password_changed_at', '>', Carbon::now()->subMinutes($minutes));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get the notifications for the user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get unread notifications for the user.
     */
    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Overrides
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Route notifications for the mail channel.
     *
     * @return string
     */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    /**
     * Get the email address that should be used for notification routing.
     *
     * @return string
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        // This is kept for Laravel's built-in password reset system
        // We're using our custom event system instead, but keeping for compatibility
        // event(new PasswordResetRequested($this, $token));
    }
}