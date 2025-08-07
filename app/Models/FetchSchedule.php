<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fetch Schedule Model
 * 
 * Manages scheduled fetching of news from MediaStack API
 */
class FetchSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'api_parameters',
        'cron_expression',
        'is_active',
        'last_run_at',
        'next_run_at',
        'total_runs',
        'successful_runs',
        'failed_runs',
        'average_execution_time',
        'max_execution_time',
        'alert_on_failure',
        'alert_email',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'api_parameters' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'average_execution_time' => 'float',
        'alert_on_failure' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get only active schedules
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get schedules that need to run
     */
    public function scopeNeedToRun(Builder $query): Builder
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', now());
            });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'name';
    }

    /**
     * Get the success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_runs === 0) {
            return 0;
        }
        return round(($this->successful_runs / $this->total_runs) * 100, 2);
    }

    /**
     * Record a successful run
     */
    public function recordSuccessfulRun(float $executionTime): void
    {
        $this->increment('total_runs');
        $this->increment('successful_runs');
        
        // Update average execution time
        $newAverage = (($this->average_execution_time * ($this->total_runs - 1)) + $executionTime) / $this->total_runs;
        $this->update([
            'average_execution_time' => round($newAverage, 2),
            'last_run_at' => now(),
        ]);
    }

    /**
     * Record a failed run
     */
    public function recordFailedRun(): void
    {
        $this->increment('total_runs');
        $this->increment('failed_runs');
        $this->update(['last_run_at' => now()]);
    }
}