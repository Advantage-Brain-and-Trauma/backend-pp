<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AnalyticsController;

// ============================================================
// PUBLIC ROUTES — No authentication required
// ============================================================

// Standalone public form (not assigned to a patient)
Route::get('/f/{slug}', [FormController::class, 'publicForm'])->name('forms.public');
Route::post('/f/{slug}/submit', [FormController::class, 'submitPublicForm'])->name('forms.submit');

// Public funnel (not assigned to a patient)
Route::get('/funnel/{slug}', [FunnelController::class, 'publicFunnel'])->name('funnels.public');
Route::post('/funnel/{slug}/submit', [FunnelController::class, 'submitPublicFunnel'])->name('funnels.submit');
Route::post('/funnel/{slug}/submit-step/{formId}', [FunnelController::class, 'submitFunnelStep'])->name('funnels.submit_step');

// ============================================================
// AUTH ROUTES
// ============================================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ============================================================
// ADMIN ROUTES — Protected by auth middleware
// ============================================================
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // ---- Appointments (read-only, fetched from external system) ----
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{id}', [AppointmentController::class, 'show'])->name('appointments.show');

    // ---- Forms ----
    Route::resource('forms', FormController::class);
    Route::get('/forms/{form}/builder', [FormController::class, 'builder'])->name('forms.builder');
    // AJAX: Save form field schema from builder
    Route::post('/forms/{form}/schema', [FormController::class, 'saveSchema'])->name('forms.schema');
    // AJAX: Publish form
    Route::post('/forms/{form}/publish', [FormController::class, 'publish'])->name('forms.publish');
    Route::get('/forms/{form}/public-url', [FormController::class, 'getPublicUrl'])->name('forms.public-url');
    Route::post('/forms/{form}/toggle-status', [FormController::class, 'toggleStatus'])->name('forms.toggle-status');
    Route::post('/forms/{form}/duplicate', [FormController::class, 'duplicate'])->name('forms.duplicate');

    // ---- Funnels ----
    Route::resource('funnels', FunnelController::class);
    // AJAX: Save funnel form_ids from builder
    Route::post('/funnels/{funnel}/schema', [FunnelController::class, 'saveSchema'])->name('funnels.schema');
    Route::post('/funnels/{funnel}/publish', [FunnelController::class, 'publish'])->name('funnels.publish');

    // ---- Analytics & Reports ----
    Route::get('/analytics/funnels', [AnalyticsController::class, 'funnels'])->name('analytics.funnels');
    Route::get('/analytics/forms', [AnalyticsController::class, 'forms'])->name('analytics.forms');
    Route::get('/analytics/reports', [AnalyticsController::class, 'reports'])->name('analytics.reports');

    // ---- User Management ----
    Route::get('/user-management', [\App\Http\Controllers\UserManagementController::class, 'index'])->name('user-management.index');
    Route::post('/user-management', [\App\Http\Controllers\UserManagementController::class, 'store'])->name('user-management.store');
    Route::put('/user-management/{user}', [\App\Http\Controllers\UserManagementController::class, 'update'])->name('user-management.update');
    Route::delete('/user-management/{user}', [\App\Http\Controllers\UserManagementController::class, 'destroy'])->name('user-management.destroy');
    Route::post('/user-management/{user}/toggle-status', [\App\Http\Controllers\UserManagementController::class, 'toggleStatus'])->name('user-management.toggle-status');

    // ---- Billing ----
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/{id}', [BillingController::class, 'show'])->name('billing.show');

    // ---- Messages ----
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/create', [MessageController::class, 'create'])->name('messages.create');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{message}/reply', [MessageController::class, 'reply'])->name('messages.reply');
});
