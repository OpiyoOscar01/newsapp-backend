<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class VisitorLog extends Model
{
    use HasUuids;

    protected $table = 'visitor_logs';
    
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
        'additional_data'
    ];

    protected $casts = [
        'additional_data' => 'array',
        'created_at' => 'datetime'
    ];

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    // Scope for filtering by date range
    public function scopeDateRange($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Scope for filtering by page type
    public function scopePageType($query, $type)
    {
        return $query->where('page_type', $type);
    }

    // Scope for filtering by device type
    public function scopeDeviceType($query, $device)
    {
        return $query->where('device_type', $device);
    }

    // Scope for filtering by referrer type
    public function scopeReferrerType($query, $referrer)
    {
        return $query->where('referrer_type', $referrer);
    }
}