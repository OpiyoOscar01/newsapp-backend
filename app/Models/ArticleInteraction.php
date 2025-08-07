<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Article Interaction Model
 * 
 * Tracks user interactions with articles for analytics
 */
class ArticleInteraction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'article_id',
        'user_id',
        'interaction_type',
        'session_id',
        'ip_address',
        'user_agent',
        'referrer',
        'metadata',
        'interaction_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'interaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the article this interaction belongs to
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Scope to filter by interaction type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope to get today's interactions
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('interaction_date', today());
    }

    /**
     * Scope to get interactions from the last N days
     */
    public function scopeLastDays(Builder $query, int $days): Builder
    {
        return $query->where('interaction_date', '>=', now()->subDays($days));
    }
}