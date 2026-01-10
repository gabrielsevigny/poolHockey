<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pool extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'draft_start_date',
        'draft_end_date',
        'rule_setting_id',
        'owner_id',
        'status',
    ];

    /**
     * Get the rule setting for this pool.
     */
    public function ruleSetting(): BelongsTo
    {
        return $this->belongsTo(RuleSetting::class);
    }

    /**
     * Get the users associated with this pool.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Get the players selected in this pool.
     */
    public function poolPlayers(): HasMany
    {
        return $this->hasMany(PoolPlayer::class);
    }

    /**
     * Get the owner of this pool.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'draft_start_date' => 'date',
            'draft_end_date' => 'date',
            'status' => 'string',
        ];
    }

    /**
     * Calculate the pool status based on dates.
     *
     * Logic:
     * - Before start_date = "selection" (preparation)
     * - ON start_date = "selection" (draft day - 1 day only!)
     * - After start_date until end_date = "active" (pool running)
     * - After end_date = "finished"
     */
    public function calculateStatus(): string
    {
        $now = now()->startOfDay();

        // Pool has ended
        if ($now->gt($this->end_date)) {
            return 'finished';
        }

        // Pool is active (day AFTER start_date until end_date)
        if ($now->gt($this->start_date) && $now->lte($this->end_date)) {
            return 'active';
        }

        // Draft day (ON start_date) OR before start_date = selection
        // This means: start_date is the draft day, pool becomes active the next day
        if ($now->lte($this->start_date)) {
            return 'selection';
        }

        return 'selection';
    }

    /**
     * Update the pool status based on current date.
     */
    public function updateStatus(): void
    {
        $this->update(['status' => $this->calculateStatus()]);
    }
}
