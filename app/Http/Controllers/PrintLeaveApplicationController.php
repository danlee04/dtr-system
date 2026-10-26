<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * HR's HRM-07 leave form, filled in, for the applicant and the officers to
 * sign. Staff print any application; an employee prints only their own.
 */
class PrintLeaveApplicationController extends Controller
{
    public function __invoke(LeaveApplication $leaveApplication): View
    {
        Gate::authorize('print', $leaveApplication);

        return view('leave.print', [
            'application' => $leaveApplication->load(['employee.office', 'ledgerEntries']),
            'form' => config('leave.form'),
        ]);
    }
}
