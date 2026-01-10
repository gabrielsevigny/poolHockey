<?php

use App\Http\Controllers\Admin\RuleSettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Rule Settings Management
    Route::get('rule-settings', [RuleSettingController::class, 'index'])->name('rule-settings.index');
    Route::post('rule-settings', [RuleSettingController::class, 'store'])->name('rule-settings.store');
    Route::patch('rule-settings/{ruleSetting}', [RuleSettingController::class, 'update'])->name('rule-settings.update');
    Route::delete('rule-settings/{ruleSetting}', [RuleSettingController::class, 'destroy'])->name('rule-settings.destroy');

    // User Management
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.update-role');
    Route::patch('users/{user}/toggle-ban', [UserController::class, 'toggleBan'])->name('users.toggle-ban');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
