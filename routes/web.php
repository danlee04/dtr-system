<?php

use App\Http\Controllers\PrintLeaveApplicationController;
use Illuminate\Support\Facades\Route;

// Every screen is the Filament panel, starting at `/`. These are the plain
// pages beside it, made to be printed.
Route::middleware('auth')->group(function (): void {
    Route::get('/leave-applications/{leaveApplication}/print', PrintLeaveApplicationController::class)->name('leave-applications.print');
});
