<?php

namespace App\Filament\Resources\DtrUploads\Actions;

use App\Enums\DtrSlot;
use App\Enums\ManualTapReason;
use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\ManualTap;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * HR copies into a DTR's blanks the times the employee wrote on the paper
 * and the supervisor signed. It opens from a blank ("+ Add") or from a time
 * HR filled, over the DTR's view, and shows that date's four slots: what IT
 * printed stays as printed, and only the blanks the computation needs, or the
 * times HR filled before, can be typed in.
 *
 * The date and slot come from the browser, so every step works the date out
 * again from the DTR on the server and acts on nothing else.
 */
class FillDayAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'fillDay';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->link()
            ->size(Size::Small)
            ->label(function (DtrUpload $record, array $arguments): string|Htmlable {
                $tap = self::filledTap($record, $arguments);

                // Both parts are built here; neither is text anyone typed.
                return $tap === null ? 'Add' : new HtmlString(e(DtrUploadInfolist::clock($tap->time)).' <span class="dtr-grid-hr-tag">HR</span>');
            })
            ->icon(fn (DtrUpload $record, array $arguments): ?Heroicon => self::filledTap($record, $arguments) === null ? Heroicon::Plus : null)
            ->tooltip(fn (DtrUpload $record, array $arguments): ?string => ($tap = self::filledTap($record, $arguments))
                ? "{$tap->reason->getLabel()} · ".($tap->filledBy->name ?? 'HR').", {$tap->updated_at->format('M j')}"
                : null)
            ->extraAttributes(fn (DtrUpload $record, array $arguments): array => self::triggerAttributes($record, $arguments))
            ->authorize('update')
            ->visible(fn (DtrUpload $record, array $arguments): bool => self::day($record, $arguments) !== null)
            ->modalHeading(fn (DtrUpload $record, array $arguments): string => 'Fill in '.(self::day($record, $arguments)['date'] ?? null)?->format('D, M j'))
            ->modalDescription(fn (DtrUpload $record, array $arguments): string => self::description($record, $arguments))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (DtrUpload $record, array $arguments): array => self::filledForm($record, $arguments))
            ->schema(fn (DtrUpload $record, array $arguments): array => self::fields($record, $arguments))
            ->extraModalFooterActions(fn (DtrUpload $record, array $arguments): array => [
                Action::make('removeHrTimes')
                    ->label('Remove HR times')
                    ->color('danger')
                    ->outlined()
                    ->authorize('update')
                    ->visible(fn (): bool => (self::day($record, $arguments)['filled'] ?? null)?->isNotEmpty() ?? false)
                    ->action(function () use ($record, $arguments): void {
                        // One by one, so each removal reaches the activity log.
                        (self::day($record, $arguments)['filled'] ?? new Collection)->each->delete();
                        $record->forgetComputed();

                        Notification::make()->title('HR times removed')->success()->send();
                    })
                    ->cancelParentActions('fillDay'),
            ])
            ->action(function (array $data, DtrUpload $record, array $arguments, Action $action): void {
                $day = self::day($record, $arguments);

                if ($day === null) {
                    return;
                }

                $entered = [];

                foreach ($day['editable'] as $slot) {
                    $time = $data[$slot->value] ?? null;
                    $entered[$slot->value] = filled($time) ? substr($time, 0, 5).':00' : null;
                }

                if (array_filter($entered) === [] && $day['filled']->isEmpty()) {
                    Notification::make()->title('Enter at least one time')->danger()->send();

                    $action->halt();
                }

                $values = [
                    'reason' => $data['reason'] instanceof ManualTapReason ? $data['reason'] : ManualTapReason::from($data['reason']),
                    'remarks' => filled($data['remarks'] ?? null) ? $data['remarks'] : null,
                    'filled_by_user_id' => auth()->id(),
                ];

                // shortcut: two people saving the same blank at once can hit the unique key; lock the row if HR ever works one DTR from two desks.
                DB::transaction(function () use ($entered, $day, $values, $record): void {
                    foreach ($entered as $slot => $time) {
                        $tap = $day['filled']->get($slot);

                        if ($time === null) {
                            $tap?->delete();
                        } elseif ($tap !== null) {
                            $tap->update(['time' => $time, ...$values]);
                        } else {
                            ManualTap::create(['employee_id' => $record->employee_id, 'date' => $day['date']->toDateString(), 'slot' => $slot, 'time' => $time, ...$values]);
                        }
                    }
                });

                $record->forgetComputed();
                $saved = count(array_filter($entered));

                Notification::make()
                    ->title($saved > 0 ? $saved.' '.str('time')->plural($saved).' saved' : 'HR times removed')
                    ->success()
                    ->send();
            });
    }

    /**
     * The date asked for, if it is on this DTR and has something HR may fill
     * or change: the blanks the computation lists, and the times HR filled
     * where nothing is printed.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{date: CarbonImmutable, row: ?DtrDay, filled: Collection<string, ManualTap>, editable: list<DtrSlot>}|null
     */
    public static function day(DtrUpload $record, array $arguments): ?array
    {
        $value = $arguments['date'] ?? null;

        if (! is_string($value) || ! $record->isComputed() || ! CarbonImmutable::canBeCreatedFromFormat($value, 'Y-m-d')) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        $result = $record->computed()->days[$value] ?? null;

        // A date like Feb 31 rolls over to March, and must not stand for it.
        if ($result === null || $date->toDateString() !== $value) {
            return null;
        }

        $row = $record->days->first(fn (DtrDay $day): bool => $day->date->toDateString() === $value);
        $filled = (new Collection(DtrSlot::cases()))
            ->filter(fn (DtrSlot $slot): bool => $row?->{$slot->value} === null)
            ->mapWithKeys(fn (DtrSlot $slot): array => [$slot->value => $record->filledTimes()->get("{$value} {$slot->value}")])
            ->filter();
        $editable = array_values(array_filter(
            DtrSlot::cases(),
            fn (DtrSlot $slot): bool => in_array($slot, $result->fillable, true) || $filled->has($slot->value),
        ));

        return $editable === [] ? null : ['date' => $date, 'row' => $row, 'filled' => $filled, 'editable' => $editable];
    }

    /** @param  array<string, mixed>  $arguments */
    private static function filledTap(DtrUpload $record, array $arguments): ?ManualTap
    {
        $slot = DtrSlot::tryFrom((string) ($arguments['slot'] ?? ''));

        return $slot === null ? null : self::day($record, $arguments)['filled'][$slot->value] ?? null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, string>
     */
    private static function triggerAttributes(DtrUpload $record, array $arguments): array
    {
        $day = self::day($record, $arguments);
        $slot = DtrSlot::tryFrom((string) ($arguments['slot'] ?? ''));

        if ($day === null || $slot === null) {
            return [];
        }

        $filled = $day['filled']->has($slot->value);

        return [
            'class' => $filled ? 'dtr-grid-hr' : 'dtr-grid-add',
            'aria-label' => ($filled ? 'Change' : 'Add')." {$slot->getLabel()}, {$day['date']->format('D, M j')}",
        ];
    }

    /** @param  array<string, mixed>  $arguments */
    private static function description(DtrUpload $record, array $arguments): string
    {
        $last = (self::day($record, $arguments)['filled'] ?? new Collection)->sortByDesc('updated_at')->first();

        return $last === null
            ? 'Copy the time written on the DTR and signed by the supervisor.'
            : 'Copied in by '.($last->filledBy->name ?? 'HR')." on {$last->updated_at->format('M j')} · {$last->reason->getLabel()}";
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function filledForm(DtrUpload $record, array $arguments): array
    {
        $filled = self::day($record, $arguments)['filled'] ?? new Collection;
        $first = $filled->first();

        return [
            ...$filled->map(fn (ManualTap $tap): string => substr($tap->time, 0, 5))->all(),
            'reason' => $first->reason ?? ManualTapReason::ForgotToTap,
            'remarks' => $first?->remarks,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, mixed>
     */
    private static function fields(DtrUpload $record, array $arguments): array
    {
        $day = self::day($record, $arguments);

        if ($day === null) {
            return [];
        }

        $focus = DtrSlot::tryFrom((string) ($arguments['slot'] ?? ''));

        return [
            Grid::make(['default' => 2, 'sm' => 4])
                ->schema(array_map(fn (DtrSlot $slot): TextEntry|TimePicker => match (true) {
                    in_array($slot, $day['editable'], true) => TimePicker::make($slot->value)
                        ->label($slot->getLabel())
                        // No "(optional)": none is needed on its own, but one of them is.
                        ->afterLabel(null)
                        ->seconds(false)
                        ->autofocus($slot === $focus)
                        ->helperText($day['filled']->has($slot->value) ? 'By HR' : null)
                        ->rules([self::inOrder($slot, $day)]),
                    default => TextEntry::make("shown_{$slot->value}")
                        ->label($slot->getLabel())
                        ->state(DtrUploadInfolist::clock($day['row']?->{$slot->value}) ?? '—')
                        ->helperText($day['row']?->{$slot->value} !== null ? 'Printed' : null),
                }, DtrSlot::cases())),
            Radio::make('reason')
                ->options(ManualTapReason::class)
                ->inline()
                ->required(),
            TextInput::make('remarks')
                ->label('Note')
                ->maxLength(255),
        ];
    }

    /**
     * A time falls between the times before and after it on the row, printed
     * or typed, so 5:00 AM typed for 5:00 PM is caught.
     *
     * @param  array{row: ?DtrDay, editable: list<DtrSlot>}  $day
     */
    private static function inOrder(DtrSlot $slot, array $day): Closure
    {
        return fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $slot, $day): void {
            if (blank($value)) {
                return;
            }

            $times = [];

            foreach (DtrSlot::cases() as $other) {
                $time = $day['row']?->{$other->value} ?? (in_array($other, $day['editable'], true) ? $get($other->value) : null);

                if (filled($time)) {
                    $times[$other->value] = substr($time, 0, 5);
                }
            }

            $mine = substr($value, 0, 5);
            $position = array_search($slot, DtrSlot::cases(), true);
            $before = array_reverse(array_slice(DtrSlot::cases(), 0, $position));
            $after = array_slice(DtrSlot::cases(), $position + 1);
            $previous = array_find($before, fn (DtrSlot $other): bool => isset($times[$other->value]));
            $next = array_find($after, fn (DtrSlot $other): bool => isset($times[$other->value]));

            if ($previous !== null && $mine < $times[$previous->value]) {
                $fail(DtrUploadInfolist::clock($mine)." is before the {$previous->getLabel()}, ".DtrUploadInfolist::clock($times[$previous->value]).'. Check AM or PM.');
            } elseif ($next !== null && $mine > $times[$next->value]) {
                $fail(DtrUploadInfolist::clock($mine)." is after the {$next->getLabel()}, ".DtrUploadInfolist::clock($times[$next->value]).'. Check AM or PM.');
            }
        };
    }
}
