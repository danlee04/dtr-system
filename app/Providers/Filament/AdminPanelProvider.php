<?php

namespace App\Providers\Filament;

use App\Enums\LeaveApplicationStatus;
use App\Enums\NavigationGroup;
use App\Filament\Pages\Auth\ChangePassword;
use App\Models\DtrUpload;
use App\Models\LeaveApplication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->brandName('DTR System')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            // Search on the topbar's left. The user sits at the sidebar's foot with
            // no menu to open (filament.sidebar-user), and light or dark is switched
            // beside the brand (filament.theme-toggle), as HR asked on 2026-11-10.
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->userMenu(false)
            ->renderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER, fn (): View => view('filament.theme-toggle'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): View => view('filament.sidebar-user'))
            // The topbar's right side, for staff: what is waiting for HR, and the month's main job.
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): View|string => auth()->user()?->isStaff() ? view('filament.topbar-actions', [
                'pendingLeave' => LeaveApplication::query()->where('status', LeaveApplicationStatus::Pending)->count(),
                'unmatchedDtrs' => DtrUpload::query()->whereNull('employee_id')->count(),
            ]) : '')
            ->navigationGroups(NavigationGroup::class)
            ->sidebarWidth('16rem')
            // Every page uses the screen's full width instead of a centred 80rem column.
            ->maxContentWidth(Width::Full)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): View => view('filament.sidebar'))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): View => view('filament.entries'))
            ->login()
            ->profile(ChangePassword::class, isSimple: false)
            ->colors([
                'primary' => Color::Amber,
            ])
            // One panel for everyone: HR's screens, and under Filament/Employee
            // the screens of an account linked to an employee. Each screen
            // checks the role itself.
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverResources(in: app_path('Filament/Employee/Resources'), for: 'App\Filament\Employee\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverPages(in: app_path('Filament/Employee/Pages'), for: 'App\Filament\Employee\Pages')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
