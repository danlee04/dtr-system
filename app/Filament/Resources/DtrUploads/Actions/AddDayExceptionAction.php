<?php

namespace App\Filament\Resources\DtrUploads\Actions;

use App\Enums\ExceptionType;
use App\Enums\LeaveCoverage;
use App\Filament\Resources\DayExceptions\Schemas\DayExceptionForm;
use App\Models\DayException;
use App\Models\DtrUpload;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * HR records an OB or a travel order from a DTR's day with no tap: the same
 * entry the OB & travel orders screen keeps and lists, for the DTR's
 * employee, starting on that date and covering the part of it with no tap.
 * The date comes from the browser, so it is worked out again from the DTR.
 */
class AddDayExceptionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'addDayException';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->link()
            ->size(Size::Small)
            ->label('OB/TO')
            ->icon(Heroicon::Plus)
            ->extraAttributes(fn (DtrUpload $record, array $arguments): array => [
                'class' => 'dtr-grid-ob',
                'aria-label' => 'Add an OB or travel order, '.(self::day($record, $arguments)['date'] ?? null)?->format('D, M j'),
            ])
            ->authorize(fn (): bool => auth()->user()?->can('create', DayException::class) ?? false)
            ->visible(fn (DtrUpload $record, array $arguments): bool => self::day($record, $arguments) !== null)
            ->modalHeading(fn (DtrUpload $record, array $arguments): string => 'OB or travel order from '.(self::day($record, $arguments)['date'] ?? null)?->format('D, M j'))
            ->modalDescription('From the signed slip. It is kept on the OB & travel orders screen too, where it can be changed or removed.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (DtrUpload $record, array $arguments): array => [
                'type' => ExceptionType::OfficialBusiness->value,
                'date_from' => (self::day($record, $arguments)['date'] ?? null)?->toDateString(),
                'date_to' => (self::day($record, $arguments)['date'] ?? null)?->toDateString(),
                'coverage' => self::day($record, $arguments)['coverage'] ?? LeaveCoverage::WholeDay,
            ])
            ->schema(fn (DtrUpload $record): array => [
                Grid::make(3)->schema(DayExceptionForm::fields(fn (): ?int => $record->employee_id)),
            ])
            ->action(function (array $data, DtrUpload $record, array $arguments): void {
                if (self::day($record, $arguments) === null) {
                    return;
                }

                $exception = DayException::create([
                    ...$data,
                    'employee_id' => $record->employee_id,
                    'created_by_user_id' => auth()->id(),
                ]);

                $record->forgetComputed();

                Notification::make()
                    ->title("{$exception->type->getLabel()} recorded: {$exception->dates()}")
                    ->success()
                    ->send();
            });
    }

    /**
     * The date asked for, if it is on this DTR and has a part with no tap,
     * with that part.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{date: CarbonImmutable, coverage: LeaveCoverage}|null
     */
    public static function day(DtrUpload $record, array $arguments): ?array
    {
        [$date, $result] = $record->computedDayFor($arguments['date'] ?? null) ?? [null, null];
        $coverage = $result?->untappedCoverage();

        return $coverage === null ? null : ['date' => $date, 'coverage' => $coverage];
    }
}
