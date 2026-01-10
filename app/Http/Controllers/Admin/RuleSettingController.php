<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RuleSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class RuleSettingController extends Controller
{
    /**
     * Display a listing of the rule settings.
     */
    public function index(): Response
    {
        return Inertia::render('admin/RuleSettings', [
            'ruleSettings' => RuleSetting::withCount('pools')->get(),
        ]);
    }

    /**
     * Store a newly created rule setting in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:rule_settings,name'],
            'rules' => ['required', 'array'],
            'rules.scoring_rules' => ['required', 'array', 'min:1'],
            'rules.scoring_rules.*.type' => ['required', 'string'],
            'rules.scoring_rules.*.label' => ['required', 'string'],
            'rules.scoring_rules.*.points' => ['required', 'integer', 'min:0'],
            'rules.player_limits' => ['required', 'array'],
            'rules.player_limits.max_per_user' => ['required', 'integer', 'min:1', 'max:50'],
            'rules.player_limits.by_position' => ['sometimes', 'array'],
        ], [
            'name.required' => 'Le nom du règlement est requis.',
            'name.unique' => 'Ce nom de règlement existe déjà.',
            'rules.scoring_rules.min' => 'Au moins une règle de pointage est requise.',
        ]);

        RuleSetting::create([
            'name' => $validated['name'],
            'template_type' => 'custom',
            'is_default' => false,
            'rules' => $validated['rules'],
        ]);

        return Redirect::route('admin.rule-settings.index');
    }

    /**
     * Update the specified rule setting in storage.
     */
    public function update(Request $request, RuleSetting $ruleSetting): RedirectResponse
    {
        // Protect default rule setting
        if ($ruleSetting->is_default) {
            return Redirect::back()->withErrors([
                'update' => 'Impossible de modifier le règlement Standard car il est le règlement de base.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:rule_settings,name,'.$ruleSetting->id],
            'rules' => ['required', 'array'],
            'rules.scoring_rules' => ['required', 'array', 'min:1'],
            'rules.scoring_rules.*.type' => ['required', 'string'],
            'rules.scoring_rules.*.label' => ['required', 'string'],
            'rules.scoring_rules.*.points' => ['required', 'integer', 'min:0'],
            'rules.player_limits' => ['required', 'array'],
            'rules.player_limits.max_per_user' => ['required', 'integer', 'min:1', 'max:50'],
            'rules.player_limits.by_position' => ['sometimes', 'array'],
        ], [
            'name.required' => 'Le nom du règlement est requis.',
            'name.unique' => 'Ce nom de règlement existe déjà.',
            'rules.scoring_rules.min' => 'Au moins une règle de pointage est requise.',
        ]);

        $ruleSetting->update([
            'name' => $validated['name'],
            'rules' => $validated['rules'],
        ]);

        return Redirect::route('admin.rule-settings.index');
    }

    /**
     * Remove the specified rule setting from storage.
     */
    public function destroy(RuleSetting $ruleSetting): RedirectResponse
    {
        // Protect default rule setting
        if ($ruleSetting->is_default) {
            return Redirect::back()->withErrors([
                'delete' => 'Impossible de supprimer le règlement Standard car il est le règlement de base.',
            ]);
        }

        // Check if rule setting is in use
        if ($ruleSetting->pools()->count() > 0) {
            return Redirect::back()->withErrors([
                'delete' => 'Impossible de supprimer ce règlement car il est utilisé par des pools.',
            ]);
        }

        $ruleSetting->delete();

        return Redirect::route('admin.rule-settings.index');
    }
}
