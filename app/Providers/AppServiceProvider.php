<?php

namespace App\Providers;

use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\ZktecoPunchSource;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        $this->configureFields();
        $this->configureTables();
    }

    /**
     * Every table fits a laptop or a tablet (HR, 2026-11-18): a long name or
     * heading wraps onto a second line instead of pushing the table wider than
     * the screen. A table still too wide hides its least-used columns on small
     * screens, column by column.
     */
    protected function configureTables(): void
    {
        Column::configureUsing(fn (Column $column) => $column->wrapHeader());
        TextColumn::configureUsing(fn (TextColumn $column) => $column->wrap());
    }

    /**
     * Most fields are required, so none carries an asterisk; a field that may
     * be left blank says "(optional)" beside its label (HR, 2026-11-10). The
     * mark sits outside the label, so validation messages keep the plain name.
     */
    protected function configureFields(): void
    {
        Field::configureUsing(
            fn (Field $field) => $field
                ->markAsRequired(false)
                ->afterLabel(fn (Field $component): ?Text => self::optionalMark($component)),
            isImportant: true,
        );
    }

    /**
     * Not on a switch or checkbox, which always has a value; a disabled field;
     * a field without a visible label; or a table filter, where every field is
     * optional.
     */
    private static function optionalMark(Field $field): ?Text
    {
        $skip = $field->isRequired()
            || $field->isDisabled()
            || $field->isLabelHidden()
            || blank($field->getLabel())
            || $field instanceof Toggle
            || $field instanceof Checkbox
            || Str::startsWith((string) $field->getStatePath(), ['tableFilters', 'tableDeferredFilters']);

        return $skip ? null : Text::make('(optional)')->extraAttributes(['class' => 'dtr-field-optional']);
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

        // DTR PDFs of up to 25 MB (HR, 2026-11-18); Livewire refuses anything past 12 MB by default.
        // Each field still sets its own limit, as the photo's 10 MB.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:25600']]);

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
