<?php

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Track visitor
    try {
        \App\Models\Visitor::create([
            'page_type' => 'home',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    } catch (\Exception $e) {
        // Silently fail - don't break the page
        \Illuminate\Support\Facades\Log::warning('Failed to track visitor', [
            'error' => $e->getMessage(),
        ]);
    }

    return Inertia::render('welcome');
})->name('home');

Route::get('/resume-checker', [ResumeController::class, 'index'])->name('resume-checker');
Route::post('/resume/analyze', [ResumeController::class, 'analyze'])
    ->middleware('throttle:resume-analyze') // Custom rate limiter with custom response
    ->name('resume.analyze');

Route::post('/feedback', [FeedbackController::class, 'store'])
    ->middleware('throttle:feedback') // Custom rate limiter with custom response
    ->name('feedback.store');

Route::get('/health', [HealthController::class, 'index'])->name('health');
