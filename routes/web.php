<?php

use App\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/resume-checker', [ResumeController::class, 'index'])->name('resume-checker');
Route::post('/resume/analyze', [ResumeController::class, 'analyze'])->name('resume.analyze');
