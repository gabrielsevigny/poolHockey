<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'points_per_goal',
        'points_per_assist',
        'points_per_shutout',
        'points_per_victory',
        'points_per_defeat',
        'points_per_overtime',
        'is_default',
        'max_players_per_user',
        'position_limits',
        'rules',
        'template_type',
    ];

    /**
     * Get the pools that use this rule setting.
     */
    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }

    protected function casts(): array
    {
        return [
            'points_per_goal' => 'integer',
            'points_per_assist' => 'integer',
            'points_per_shutout' => 'integer',
            'points_per_victory' => 'integer',
            'points_per_defeat' => 'integer',
            'points_per_overtime' => 'integer',
            'is_default' => 'boolean',
            'max_players_per_user' => 'integer',
            'position_limits' => 'array',
            'rules' => 'array',
        ];
    }

    /**
     * Get scoring rules from dynamic rules or fallback to legacy fields.
     */
    public function getScoringRules(): array
    {
        if ($this->rules && isset($this->rules['scoring_rules'])) {
            return $this->rules['scoring_rules'];
        }

        // Fallback to legacy fields for backward compatibility
        $legacyRules = [];

        if ($this->points_per_goal) {
            $legacyRules[] = ['type' => 'goal', 'label' => 'But', 'points' => $this->points_per_goal];
        }

        if ($this->points_per_assist) {
            $legacyRules[] = ['type' => 'assist', 'label' => 'Passe', 'points' => $this->points_per_assist];
        }

        if ($this->points_per_shutout) {
            $legacyRules[] = ['type' => 'shutout', 'label' => 'Blanchissage', 'points' => $this->points_per_shutout];
        }

        if ($this->points_per_victory) {
            $legacyRules[] = ['type' => 'victory', 'label' => 'Victoire', 'points' => $this->points_per_victory];
        }

        return $legacyRules;
    }

    /**
     * Get player limits from dynamic rules or fallback to legacy fields.
     */
    public function getPlayerLimits(): array
    {
        if ($this->rules && isset($this->rules['player_limits'])) {
            return $this->rules['player_limits'];
        }

        // Fallback to legacy fields
        return [
            'max_per_user' => $this->max_players_per_user ?? 20,
            'by_position' => $this->position_limits ?? [],
        ];
    }

    /**
     * Check if this setting uses dynamic rules.
     */
    public function usesDynamicRules(): bool
    {
        return ! empty($this->rules);
    }
}
