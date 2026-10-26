<?php

namespace App\Http\Middleware;

use App\Filament\Employee\Pages\Auth\ChangePassword;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A temporary password from HR is replaced before any page opens. Only page
 * visits are turned back: signing out and the form's own requests go through.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password
            && $request->isMethod('GET')
            && ! $request->routeIs(ChangePassword::getRouteName())) {
            return redirect()->to(ChangePassword::getUrl());
        }

        return $next($request);
    }
}
