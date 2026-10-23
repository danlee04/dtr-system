<?php

namespace App\Providers\Filament;

use App\Enums\NavigationGroup;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup as FilamentNavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
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
            ->navigationGroups($this->navigationGroups())
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): View => view('filament.navigation-groups'))
            ->login()
            ->colors([
                'primary' => Color::Amber,
                ...$this->navigationGroupColors(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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

    /**
     * The groups in the enum's order, keyed by case name as Filament matches
     * them. A group is a heading, not a dropdown, so none collapses. Each
     * carries its colour class, which points --color-* at that group's
     * palette for the sidebar styles.
     *
     * @return array<string, FilamentNavigationGroup>
     */
    private function navigationGroups(): array
    {
        return collect(NavigationGroup::cases())
            ->mapWithKeys(fn (NavigationGroup $group): array => [
                $group->name => FilamentNavigationGroup::fromEnum($group)
                    ->collapsible(false)
                    ->extraSidebarAttributes(['class' => "dtr-nav-group fi-color-{$group->colorName()}"]),
            ])
            ->all();
    }

    /** @return array<string, array<int, string>> colour name → palette */
    private function navigationGroupColors(): array
    {
        return collect(NavigationGroup::cases())
            ->mapWithKeys(fn (NavigationGroup $group): array => [$group->colorName() => $group->getColor()])
            ->all();
    }
}
