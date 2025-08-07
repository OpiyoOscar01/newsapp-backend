<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * API Fetch Log Model
 * 
 * Logs all API fetch operations for monitoring and debugging
 */
class ApiFetchLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'endpoint',
        'parameters',
        'request_type',
        'total_results',
        'fetched_results',
        'new_articles',
        'updated_articles',
        'duplicate_articles',
        'execution_time_ms',
        'api_response_time_ms',
        'db_processing_time_ms',
        'status',
        'error_message',
        'error_details',
        'http_status_code',
        'rate_limit_remaining',
        'rate_limit_reset_at',
        'triggered_by',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parameters' => 'array',
        'error_details' => 'array',
        'rate_limit_reset_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get successful logs
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to get failed logs
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get logs from today
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Get the efficiency percentage
     */
    public function getEfficiencyPercentageAttribute(): float
    {
        if ($this->total_results === 0) {
            return 0;
        }
        return round(($this->new_articles / $this->total_results) * 100, 2);
    }
}