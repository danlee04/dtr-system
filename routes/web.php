<?php

use App\Http\Controllers\PrintLeaveApplicationController;
use Illuminate\Support\Facades\Route;

// Every screen is the Filament panel, starting at `/`. These are the plain
// pages beside it, made to be printed. auth.session ends a session here too
// once its password changes, as the panel's own middleware does.
Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('/leave-applications/{leaveApplication}/print', PrintLeaveApplicationController::class)->name('leave-applications.print');
});
