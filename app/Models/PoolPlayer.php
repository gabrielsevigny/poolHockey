<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolPlayer extends Model
{
    protected $fillable = [
        'pool_id',
        'user_id',
        'nhl_player_id',
        'player_name',
        'position',
        'team_abbrev',
        'team_name',
        'headshot_url',
        'draft_order',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
