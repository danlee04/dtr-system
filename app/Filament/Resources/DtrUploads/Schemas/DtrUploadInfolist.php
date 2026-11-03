<?php

namespace App\Filament\Resources\DtrUploads\Schemas;

use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Services\Attendance\ComputedDtr;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * One uploaded DTR: whose it is, what the month comes to, then a row for
 * every date laid out like the paper form, blank where the DTR printed
 * nothing, so HR can lay it beside the PDF and check it line by line.
 */
class DtrUploadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('employee.full_name')
                            ->label('Employee')
                            ->placeholder('Needs an employee'),
                        TextEntry::make('employee_name')
                            ->label('Name on the DTR'),
                        TextEntry::make('period')
                            ->label('Month')
                            ->state(fn (DtrUpload $record): string => $record->period()),
                        TextEntry::make('file_name')
                            ->label('File'),
                    ]),
                Section::make('The Month')
                    ->contained(false)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('not_computed')
                            ->hiddenLabel()
                            ->state('Choose the employee, and the app computes this DTR against their schedule.')
                            ->color('warning')
                            ->visible(fn (DtrUpload $record): bool => $record->employee_id === null)
                            ->columnSpanFull(),
                        self::figure('late', 'Late', fn (ComputedDtr $dtr): string => self::minutes($dtr->late, $dtr->lateCount)),
                        self::figure('undertime', 'Undertime', fn (ComputedDtr $dtr): string => self::minutes($dtr->undertime, $dtr->undertimeCount)),
                        self::figure('total', 'Late + undertime', fn (ComputedDtr $dtr): string => ($dtr->late + $dtr->undertime).' min '
                            .($dtr->dayEquivalent === null ? '(earns no leave credits)' : "= {$dtr->dayEquivalent} day")),
                        self::figure('absent', 'Absent', fn (ComputedDtr $dtr): string => self::count($dtr->absent, 'day')),
                        self::figure('missing', 'Days with a missing tap', fn (ComputedDtr $dtr): string => self::count($dtr->daysMissingTaps, 'day')),
                        self::figure('no_schedule', 'Days without a schedule', fn (ComputedDtr $dtr): string => self::count($dtr->daysWithoutSchedule, 'day'))
                            ->color('warning')
                            ->visible(fn (DtrUpload $record): bool => $record->employee_id !== null && $record->computed()->daysWithoutSchedule > 0),
                    ]),
                Section::make('Days')
                    ->contained(false)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('rows')
                            ->hiddenLabel()
                            ->state(fn (DtrUpload $record): array => self::rows($record))
                            ->table([
                                TableColumn::make('Day'),
                                TableColumn::make('A.M. Arrival'),
                                TableColumn::make('A.M. Departure'),
                                TableColumn::make('P.M. Arrival'),
                                TableColumn::make('P.M. Departure'),
                                TableColumn::make('O.T.'),
                                TableColumn::make('Late (min)'),
                                TableColumn::make('Undertime (min)'),
                                TableColumn::make('Status'),
                                TableColumn::make('Notes'),
                            ])
                            ->schema([
                                TextEntry::make('date'),
                                TextEntry::make('am_arrival'),
                                TextEntry::make('am_departure'),
                                TextEntry::make('pm_arrival'),
                                TextEntry::make('pm_departure'),
                                TextEntry::make('ot'),
                                TextEntry::make('late'),
                                TextEntry::make('undertime'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('notes'),
                            ]),
                    ]),
            ]);
    }

    /** @param  callable(ComputedDtr): string  $value */
    private static function figure(string $name, string $label, callable $value): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state(fn (DtrUpload $record): string => $value($record->computed()))
            ->visible(fn (DtrUpload $record): bool => $record->employee_id !== null);
    }

    /** @return list<array<string, mixed>> */
    private static function rows(DtrUpload $record): array
    {
        $printed = $record->days->keyBy(fn (DtrDay $day): string => $day->date->toDateString());
        $computed = $record->computed()->days;
        $rows = [];

        foreach (CarbonPeriod::create($record->period_start, $record->period_end) as $date) {
            $day = $printed->get($date->toDateString());
            $result = $computed[$date->toDateString()] ?? null;

            $rows[] = [
                'date' => $date->format('D, M j'),
                'am_arrival' => self::time($day?->am_arrival),
                'am_departure' => self::time($day?->am_departure),
                'pm_arrival' => self::time($day?->pm_arrival),
                'pm_departure' => self::time($day?->pm_departure),
                'ot' => implode('–', array_filter([self::time($day?->ot_arrival), self::time($day?->ot_departure)])) ?: null,
                'late' => $result?->late ?: null,
                'undertime' => $result?->undertime ?: null,
                'status' => $result?->status,
                'notes' => implode(' · ', array_filter([
                    $result?->note,
                    $result?->missing ? 'No '.implode(', ', $result->missing) : null,
                    $day?->remarks,
                ])) ?: null,
            ];
        }

        return $rows;
    }

    /** "23 min, 2 times", or "None". */
    private static function minutes(int $minutes, int $times): string
    {
        return $minutes === 0 ? 'None' : "{$minutes} min, ".self::count($times, 'time');
    }

    private static function count(int|float $number, string $unit): string
    {
        return $number.' '.Str::plural($unit, (float) $number === 1.0 ? 1 : 2);
    }

    /** "17:13" or "17:13:00", as the database hands it back, to "5:13 PM". */
    private static function time(?string $time): ?string
    {
        return $time === null ? null : CarbonImmutable::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A');
    }
}
