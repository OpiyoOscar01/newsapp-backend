<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'status',
        'preferences',
        'ip_address',
        'user_agent',
        'subscribed_at',
        'unsubscribed_at',
        'last_sent_at',
    ];

    protected $casts = [
        'preferences' => 'array',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSubscribed($query)
    {
        return $query->where('status', 'active');
    }

    // Methods
    public function unsubscribe(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
    }

    public function markAsBounced(): void
    {
        $this->update(['status' => 'bounced']);
    }

    public function recordSent(): void
    {
        $this->update(['last_sent_at' => now()]);
    }
}