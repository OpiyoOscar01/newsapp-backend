<?php
// app/Models/ArticleInteraction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleInteraction extends Model
{
    use HasFactory;

    protected $table = 'article_interactions';

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
        'comment_content',
        'parent_comment_id',
        'is_edited',
        'edited_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'interaction_date' => 'datetime',
        'edited_at' => 'datetime',
        'is_edited' => 'boolean',
    ];

    // Relationships
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentComment(): BelongsTo
    {
        return $this->belongsTo(ArticleInteraction::class, 'parent_comment_id');
    }

    public function replies()
    {
        return $this->hasMany(ArticleInteraction::class, 'parent_comment_id')->where('interaction_type', 'comment');
    }

    // Scopes
    public function scopeLikes($query)
    {
        return $query->where('interaction_type', 'like');
    }

    public function scopeShares($query)
    {
        return $query->where('interaction_type', 'share');
    }

    public function scopeViews($query)
    {
        return $query->where('interaction_type', 'view');
    }

    public function scopeBookmarks($query)
    {
        return $query->where('interaction_type', 'bookmark');
    }

    public function scopeComments($query)
    {
        return $query->where('interaction_type', 'comment')
                     ->whereNull('parent_comment_id');
    }

    // Helper methods
    public function isLike(): bool
    {
        return $this->interaction_type === 'like';
    }

    public function isComment(): bool
    {
        return $this->interaction_type === 'comment';
    }

    public function getCommentContentAttribute($value)
    {
        return $value ?? ($this->metadata['comment'] ?? null);
    }
}