<?php

namespace App\Filament\Resources\DtrUploads\Schemas;

use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Services\Attendance\DtrPdfReader;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One uploaded DTR, laid out like the paper form: whose it is, then a row for
 * every date of the month, blank where the DTR printed nothing, so HR can lay
 * it beside the PDF and check it line by line.
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
                                TableColumn::make('O.T. Arrival'),
                                TableColumn::make('O.T. Departure'),
                                TableColumn::make('Remarks'),
                            ])
                            ->schema([
                                TextEntry::make('date'),
                                ...array_map(fn (string $column): TextEntry => TextEntry::make($column), DtrPdfReader::COLUMNS),
                                TextEntry::make('remarks'),
                            ]),
                    ]),
            ]);
    }

    /** @return list<array<string, ?string>> */
    private static function rows(DtrUpload $record): array
    {
        $days = $record->days->keyBy(fn (DtrDay $day): string => $day->date->toDateString());
        $rows = [];

        foreach (CarbonPeriod::create($record->period_start, $record->period_end) as $date) {
            $day = $days->get($date->toDateString());

            $rows[] = [
                'date' => $date->format('D, M j'),
                ...array_combine(DtrPdfReader::COLUMNS, array_map(fn (string $column): ?string => self::time($day?->{$column}), DtrPdfReader::COLUMNS)),
                'remarks' => $day?->remarks,
            ];
        }

        return $rows;
    }

    /** "17:13" or "17:13:00", as the database hands it back, to "5:13 PM". */
    private static function time(?string $time): ?string
    {
        return $time === null ? null : CarbonImmutable::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A');
    }
}
