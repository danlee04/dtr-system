<?php

use App\Http\Controllers\LoginSlipsController;
use App\Http\Controllers\PrintLeaveApplicationController;
use Illuminate\Support\Facades\Route;

// Every screen is a Filament panel: HR at `/`, employees at `/employee`.
// These are the plain pages beside them, made to be printed.
Route::middleware('auth')->group(function (): void {
    Route::get('/employee-logins/slips', LoginSlipsController::class)->name('employee-logins.slips');
    Route::get('/leave-applications/{leaveApplication}/print', PrintLeaveApplicationController::class)->name('leave-applications.print');
});
