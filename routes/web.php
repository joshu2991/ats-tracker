<?php

use App\Http\Controllers\FeedbackController;
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
Route::post('/resume/analyze', [ResumeController::class, 'analyze'])->name('resume.analyze');

Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
