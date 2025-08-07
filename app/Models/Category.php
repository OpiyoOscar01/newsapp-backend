<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Category Model
 * 
 * Represents news categories that match MediaStack API categories
 * 
 * @property int $id
 * @property string $slug Category slug (e.g., technology, business)
 * @property string $name Display name for the category
 * @property string|null $description Optional description of the category
 * @property bool $is_active Whether this category is currently active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all articles in this category
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'category', 'slug');
    }

    /**
     * Get all sources that belong to this category
     */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class, 'category', 'slug');
    }

    /**
     * Scope to get only active categories
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get categories with articles
     */
    public function scopeWithArticles(Builder $query): Builder
    {
        return $query->whereHas('articles');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get articles count for this category
     */
    public function getArticlesCountAttribute(): int
    {
        return $this->articles()->where('is_active', true)->count();
    }

    /**
     * Activate the category
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the category
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }
}