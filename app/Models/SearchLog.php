<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'search_logs';

    /**
     * Indicates if the model should be timestamped.
     * Only created_at, no updated_at
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'query',
        'type',
        'ip_address',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that performed the search.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a search query
     */
    public static function logSearch($query, $userId = null, $type = 'general', $ipAddress = null)
    {
        // Don't log empty queries
        if (empty(trim($query))) {
            return null;
        }

        return self::create([
            'user_id' => $userId,
            'query' => trim($query),
            'type' => $type,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Get trending searches
     */
    public static function getTrending($days = 7, $limit = 10)
    {
        return self::where('created_at', '>=', now()->subDays($days))
            ->select('query', \DB::raw('count(*) as search_count'))
            ->groupBy('query')
            ->orderBy('search_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'query' => $item->query,
                    'count' => $item->search_count,
                ];
            });
    }

    /**
     * Get user's recent searches
     */
    public static function getUserRecent($userId, $limit = 10)
    {
        return self::where('user_id', $userId)
            ->select('query')
            ->distinct()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->pluck('query');
    }
}
