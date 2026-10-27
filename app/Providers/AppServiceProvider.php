<?php

namespace App\Providers;

use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\ZktecoPunchSource;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PunchSource::class, ZktecoPunchSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModals();
    }

    /**
     * Every record modal behaves alike: its heading and buttons stay in view
     * while a long form scrolls, and a create closes the modal when it is
     * done, as filing leave does.
     */
    protected function configureModals(): void
    {
        CreateAction::configureUsing(fn (CreateAction $action) => $action->stickyModalHeader()->stickyModalFooter()->createAnother(false));
        EditAction::configureUsing(fn (EditAction $action) => $action->stickyModalHeader()->stickyModalFooter());
        ViewAction::configureUsing(fn (ViewAction $action) => $action->stickyModalHeader()->stickyModalFooter());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Attendance arithmetic moves times around constantly. An immutable
        // date cannot be changed underneath the code that is still using it.
        Date::use(CarbonImmutable::class);

        // Without this, a link built by the framework can come back as http
        // even on an https site, and the browser downgrades the request —
        // carrying the session cookie with it.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
