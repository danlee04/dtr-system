<?php

namespace App\Providers\Filament;

use App\Enums\NavigationGroup;
use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\DtrUploads\Pages\ListDtrUploads;
use App\Services\WaitingForHr;
use Carbon\CarbonImmutable;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Tables\View\TablesRenderHook;
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
            ->brandLogoHeight('3rem')
            // Search on the topbar's left. The user sits at the sidebar's foot with
            // no menu to open (filament.sidebar-user), as HR asked on 2026-11-10.
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->userMenu(false)
            // Staff only: an employee's pages have no topbar, and on a phone the sidebar's own button (HR, 2026-11-23).
            ->topbar(fn (): bool => auth()->user()?->isStaff() ?? true)
            // Employees only: what HR did with their leave and DTR (2026-11-24). Staff have "Waiting for you" in the topbar.
            // Placed by hand, at the right of each page's title (filament.employee.notifications-bell), not in the sidebar.
            ->databaseNotifications(fn (): bool => ! (auth()->user()?->isStaff() ?? true), position: DatabaseNotificationsPosition::Topbar)
            // The dashboard draws its own heading, and the bell beside it.
            ->renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, fn (array $scopes): string => in_array(Dashboard::class, $scopes, true) ? '' : view('filament.employee.notifications-bell')->render())
            // Light only (HR, 2026-11-23).
            ->darkMode(false)
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): View => view('filament.sidebar-user'))
            // The DTR list's Month and Needs an employee filters, in its toolbar right of the search.
            ->renderHook(TablesRenderHook::TOOLBAR_SEARCH_AFTER, fn (): View => view('filament.dtr-uploads.toolbar-filters'), scopes: ListDtrUploads::class)
            // The topbar's right side, for staff: what is waiting for HR, New, and the month's main job.
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): View|string => auth()->user()?->isStaff()
                ? view('filament.topbar-actions', app(WaitingForHr::class)->counts(CarbonImmutable::today()))
                : '')
            ->navigationGroups(NavigationGroup::class)
            ->sidebarWidth('16rem')
            // Every page uses the screen's full width instead of a centred 80rem column.
            ->maxContentWidth(Width::Full)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): View => view('filament.sidebar'))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): View => view('filament.entries'))
            ->login()
            ->profile(ChangePassword::class, isSimple: false)
            ->colors([
                'primary' => Color::Blue,
                // The Sick card's lavender on an employee's page (HR's reference, 2026-11-23).
                'violet' => Color::Violet,
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
