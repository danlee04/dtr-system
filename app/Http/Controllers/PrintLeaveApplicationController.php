<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * HR's HRM-07 leave form, filled in, for the applicant and the officers to
 * sign. Staff print any application; an employee prints only their own.
 */
class PrintLeaveApplicationController extends Controller
{
    public function __invoke(Request $request, LeaveApplication $leaveApplication): View
    {
        // The panel's own rule, since this page sits outside it: a deactivated
        // account, or one whose employee has left, prints nothing.
        abort_unless($request->user()->canAccessPanel(Filament::getDefaultPanel()), 403);

        Gate::authorize('print', $leaveApplication);

        return view('leave.print', [
            'application' => $leaveApplication->load(['employee.office', 'ledgerEntries']),
            'form' => config('leave.form'),
        ]);
    }
}
