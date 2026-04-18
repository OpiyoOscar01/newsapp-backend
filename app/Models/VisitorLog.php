<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;

class VisitorLog extends Model
{
    use HasUuids;

    protected $table = 'visitor_logs';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'unique_visitor_id',
        'page',
        'page_type',
        'referrer',
        'referrer_type',
        'user_agent',
        'screen_resolution',
        'device_type',
        'country',
        'city',
        'timezone',
        'category_slug',
        'article_id',
        'ip_address',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeDateRange(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days)->startOfDay());
    }

    public function scopePageType(Builder $query, string $type): Builder
    {
        return $query->where('page_type', $type);
    }

    public function scopeDeviceType(Builder $query, string $device): Builder
    {
        return $query->where('device_type', $device);
    }

    public function scopeReferrerType(Builder $query, string $referrer): Builder
    {
        return $query->where('referrer_type', $referrer);
    }

    public function scopeRecentActive(Builder $query, int $minutes = 5): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
