<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * The temporary passwords just given out, to print and hand over. They wait
 * in the session until this page takes them, so a refresh shows none, and a
 * request in between, which would use up a flash, cannot lose them.
 */
class LoginSlipsController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('manageLogin', Employee::class);

        $slips = session()->pull('login_slips');

        return view('leave.login-slips', [
            'slips' => $slips === null ? [] : decrypt($slips),
            'signInUrl' => Filament::getPanel('employee')->getLoginUrl(),
            'backUrl' => EmployeeResource::getUrl(panel: 'admin'),
        ]);
    }
}
