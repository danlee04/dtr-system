<?php

namespace App\Filament\Resources\DtrUploads\Actions;

use App\Filament\Resources\DayExceptions\Schemas\DayExceptionForm;
use App\Models\DayException;
use App\Models\DtrUpload;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * HR changes or removes, from a DTR's day, an OB, travel order, leave or gate
 * pass recorded by mistake (HR, 2026-11-23): the same entry the OB, TO, leave
 * & gate pass screen lists. Leave filed as an application is changed on the
 * leave screens, not here.
 * The entry's id comes from the browser, so only an entry of this DTR's
 * employee that touches its dates is acted on.
 */
class EditDayExceptionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editDayException';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->link()
            ->size(Size::Small)
            // "Edit" only, small, beside the badge that already names the entry (HR, 2026-11-23).
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->tooltip(fn (DtrUpload $record, array $arguments): ?string => self::description(self::exception($record, $arguments)))
            ->extraAttributes(fn (DtrUpload $record, array $arguments): array => ($exception = self::exception($record, $arguments)) === null ? [] : [
                'class' => 'dtr-grid-ob dtr-grid-edit',
                'aria-label' => self::description($exception),
            ])
            // A final DTR is locked: what it deducted must keep matching it.
            ->authorize(fn (DtrUpload $record, array $arguments): bool => ($exception = self::exception($record, $arguments)) !== null
                && (auth()->user()?->can('update', $exception) ?? false)
                && auth()->user()->can('update', $record))
            ->visible(fn (DtrUpload $record, array $arguments): bool => self::exception($record, $arguments) !== null)
            ->modalHeading(fn (DtrUpload $record, array $arguments): string => ($exception = self::exception($record, $arguments)) === null ? '' : "{$exception->type->getLabel()}: {$exception->dates()}")
            ->modalDescription('From the signed paper. A change here is a change on the OB, TO, leave & gate pass screen too.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (DtrUpload $record, array $arguments): array => ($exception = self::exception($record, $arguments)) === null ? [] : [
                'type' => $exception->type->value,
                'date_from' => $exception->date_from->toDateString(),
                'date_to' => $exception->date_to->toDateString(),
                'coverage' => $exception->coverage->value,
                'reference_no' => $exception->reference_no,
                'remarks' => $exception->remarks,
            ])
            ->schema(fn (DtrUpload $record, array $arguments): array => [
                Grid::make(3)->schema(DayExceptionForm::fields(fn (): ?int => $record->employee_id, self::exception($record, $arguments))),
            ])
            ->extraModalFooterActions(fn (DtrUpload $record, array $arguments): array => [
                Action::make('removeDayException')
                    ->label('Remove')
                    ->color('danger')
                    ->outlined()
                    ->authorize(fn (): bool => ($exception = self::exception($record, $arguments)) !== null
                        && (auth()->user()?->can('delete', $exception) ?? false)
                        && auth()->user()->can('update', $record))
                    ->action(function () use ($record, $arguments): void {
                        $exception = self::exception($record, $arguments);

                        if ($exception === null) {
                            return;
                        }

                        $exception->delete();
                        $record->forgetComputed();

                        Notification::make()->title("{$exception->type->getLabel()} removed: {$exception->dates()}")->success()->send();
                    })
                    ->cancelParentActions('editDayException'),
            ])
            ->action(function (array $data, DtrUpload $record, array $arguments): void {
                $exception = self::exception($record, $arguments);

                if ($exception === null) {
                    return;
                }

                $exception->update($data);
                $record->forgetComputed();

                Notification::make()->title("{$exception->type->getLabel()} updated: {$exception->dates()}")->success()->send();
            });
    }

    /**
     * The entry asked for, if it is one of this computed DTR's.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function exception(DtrUpload $record, array $arguments): ?DayException
    {
        $id = $arguments['exception'] ?? null;

        return is_scalar($id) && $record->isComputed() ? $record->dayExceptions()->get((string) $id) : null;
    }

    /** "Change or remove the official business, Oct 5–6, 2026": which entry the bare "Edit" opens. */
    private static function description(?DayException $exception): ?string
    {
        return $exception === null ? null : 'Change or remove the '.Str::lower($exception->type->getLabel()).', '.$exception->dates();
    }
}
