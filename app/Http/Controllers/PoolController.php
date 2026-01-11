<?php

namespace App\Http\Controllers;

use App\Models\Pool;
use App\Models\RuleSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class PoolController extends Controller
{
    /**
     * Store a newly created pool in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'rule_setting_id' => ['nullable', 'exists:rule_settings,id'],
            'custom_rules' => ['nullable', 'array'],
            'custom_rules.scoring_rules' => ['required_with:custom_rules', 'array', 'min:1'],
            'custom_rules.scoring_rules.*.type' => ['required_with:custom_rules', 'string'],
            'custom_rules.scoring_rules.*.label' => ['required_with:custom_rules', 'string'],
            'custom_rules.scoring_rules.*.points' => ['required_with:custom_rules', 'integer', 'min:0'],
            'custom_rules.player_limits' => ['required_with:custom_rules', 'array'],
            'custom_rules.player_limits.max_per_user' => ['required_with:custom_rules', 'integer', 'min:1', 'max:50'],
            'custom_rules.player_limits.by_position' => ['nullable', 'array'],
            'custom_rules.player_limits.by_position.*.min' => ['nullable', 'integer', 'min:0', 'max:20'],
            'custom_rules.player_limits.by_position.*.max' => ['nullable', 'integer', 'min:0', 'max:20'],
        ], [
            'name.required' => 'Le nom du pool est requis.',
            'start_date.required' => 'La date de début est requise.',
            'end_date.required' => 'La date de fin est requise.',
            'end_date.after' => 'La date de fin doit être après la date de début.',
            'rule_setting_id.exists' => 'Le règlement sélectionné n\'existe pas.',
            'custom_rules.scoring_rules.min' => 'Au moins une règle de pointage est requise.',
        ]);

        // Determine which rule setting to use
        $ruleSettingId = $validated['rule_setting_id'] ?? null;

        // If custom rules are provided, create a new rule setting
        if (! empty($validated['custom_rules'])) {
            $ruleSetting = RuleSetting::create([
                'name' => $validated['name'].' - Règles personnalisées',
                'template_type' => 'custom',
                'is_default' => false,
                'rules' => $validated['custom_rules'],
            ]);

            $ruleSettingId = $ruleSetting->id;
        }

        // Ensure we have a rule setting
        if (! $ruleSettingId) {
            return Redirect::back()->withErrors([
                'rule_setting_id' => 'Veuillez sélectionner un règlement ou créer des règles personnalisées.',
            ]);
        }

        $pool = Pool::create([
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'rule_setting_id' => $ruleSettingId,
            'owner_id' => $request->user()->id,
            'status' => 'selection',
        ]);

        // Calculate and update status based on dates
        $pool->updateStatus();

        // Automatically add the pool owner as a participant
        $pool->users()->attach($request->user()->id);

        return Redirect::route('dashboard');
    }

    /**
     * Mark the current user's selection as completed for this pool.
     */
    public function completeSelection(Request $request, Pool $pool): RedirectResponse
    {
        // Verify the user is part of this pool
        if (! $pool->users->contains($request->user()->id)) {
            abort(403, 'Vous ne faites pas partie de ce pool.');
        }

        // Update the pivot table to mark selection as completed
        $pool->users()->updateExistingPivot($request->user()->id, [
            'selection_completed_at' => now(),
        ]);

        return Redirect::back();
    }

    /**
     * Remove a participant from the pool.
     */
    public function removeParticipant(Request $request, Pool $pool, User $user): RedirectResponse
    {
        // Verify the current user is the pool admin (owner or superAdmin)
        $currentUser = $request->user();
        $isPoolAdmin = $currentUser->hasRole('superAdmin') || $pool->owner_id === $currentUser->id;

        if (! $isPoolAdmin) {
            abort(403, 'Vous n\'avez pas la permission de retirer des participants de ce pool.');
        }

        // Prevent removing the pool owner
        if ($user->id === $pool->owner_id) {
            return Redirect::back()->withErrors([
                'participant' => 'Le propriétaire du pool ne peut pas être retiré.',
            ]);
        }

        // Verify the user is actually a participant
        if (! $pool->users->contains($user->id)) {
            return Redirect::back()->withErrors([
                'participant' => 'Cet utilisateur ne fait pas partie de ce pool.',
            ]);
        }

        // Delete all players selected by this participant
        $pool->poolPlayers()->where('user_id', $user->id)->delete();

        // Remove the participant from the pool
        $pool->users()->detach($user->id);

        return Redirect::back()->with('success', 'Le participant a été retiré du pool avec succès.');
    }
}
