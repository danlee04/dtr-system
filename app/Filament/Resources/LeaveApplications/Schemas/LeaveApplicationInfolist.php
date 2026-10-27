<?php

namespace App\Filament\Resources\LeaveApplications\Schemas;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LedgerKind;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

/**
 * One application, read top down: what and when, then the days it took and
 * what they were charged to, then the details the form carries, the days one
 * by one, and who filed, approved or cancelled it.
 */
class LeaveApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // One line: what, when, and where it stands.
                Flex::make([
                    TextEntry::make('leave_type')
                        ->hiddenLabel()
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold)
                        ->grow(false),
                    TextEntry::make('dates')
                        ->hiddenLabel()
                        ->state(fn (LeaveApplication $record): string => $record->dates())
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->color('gray'),
                    TextEntry::make('status')
                        ->hiddenLabel()
                        ->badge()
                        ->grow(false),
                ])
                    ->verticallyAlignCenter()
                    ->columnSpanFull(),
                Grid::make(4)
                    ->columnSpanFull()
                    ->schema([
                        self::days('days', 'Days'),
                        self::days('days_with_pay', 'With pay'),
                        self::days('days_without_pay', 'Without pay')
                            ->color(fn (string $state): string => bccomp($state, '0', 3) > 0 ? 'danger' : 'gray'),
                        TextEntry::make('charged_to')
                            ->label('Charged to')
                            ->state(fn (LeaveApplication $record): ?string => self::chargedTo($record))
                            ->placeholder('Nothing: all without pay'),
                    ]),
                Section::make('Details')
                    ->contained(false)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        // An employee reading their own leave knows whose it is.
                        TextEntry::make('employee.full_name')
                            ->label('Employee')
                            ->hidden(fn (LeaveApplication $record): bool => self::isOwn($record)),
                        TextEntry::make('employee.office.name')
                            ->label('Office')
                            ->placeholder('—')
                            ->hidden(fn (LeaveApplication $record): bool => self::isOwn($record)),
                        TextEntry::make('division_head')
                            ->label('Division Head (7.B)')
                            ->placeholder('—'),
                        TextEntry::make('commutation')
                            ->label('Commutation')
                            ->state(fn (LeaveApplication $record): string => $record->commutation_requested ? 'Requested' : 'Not requested'),
                        // Last, so the four short entries above share one row.
                        TextEntry::make('details')
                            ->label('Leave details')
                            ->state(fn (LeaveApplication $record): ?string => implode(' — ', array_filter([$record->detail?->getLabel(), $record->detail_text])) ?: null)
                            ->visible(fn (LeaveApplication $record): bool => filled($record->detail) || filled($record->detail_text))
                            ->columnSpanFull(),
                    ]),
                Section::make('Days by Date')
                    ->contained(false)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('days_by_date')
                            ->hiddenLabel()
                            ->state(fn (LeaveApplication $record): array => self::daysByDate($record))
                            ->table([
                                TableColumn::make('Date'),
                                TableColumn::make('Days'),
                                TableColumn::make('Without pay'),
                            ])
                            ->schema([
                                TextEntry::make('date'),
                                TextEntry::make('days'),
                                TextEntry::make('without_pay')
                                    ->placeholder('—')
                                    ->color('danger'),
                            ]),
                    ]),
                Section::make('History')
                    ->contained(false)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('filed')
                            ->label('Filed')
                            ->icon(Heroicon::OutlinedDocumentPlus)
                            ->iconColor('gray')
                            ->state(fn (LeaveApplication $record): string => self::byLine($record->filedBy?->name, $record->created_at)),
                        TextEntry::make('approved')
                            ->label('Approved')
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->iconColor('success')
                            ->state(fn (LeaveApplication $record): ?string => $record->approved_at === null ? null : self::byLine($record->approvedBy?->name, $record->approved_at))
                            ->visible(fn (LeaveApplication $record): bool => $record->approved_at !== null),
                        TextEntry::make('cancelled')
                            ->label('Cancelled')
                            ->icon(Heroicon::OutlinedXCircle)
                            ->iconColor('danger')
                            ->state(fn (LeaveApplication $record): ?string => $record->cancelled_at === null ? null : self::byLine($record->cancelledBy?->name, $record->cancelled_at)." — {$record->cancel_reason}")
                            ->visible(fn (LeaveApplication $record): bool => $record->cancelled_at !== null),
                    ]),
            ]);
    }

    private static function days(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->size(TextSize::Large)
            ->weight(FontWeight::Bold);
    }

    /** "SL 2.000 · VL 1.000": the credits the application drew, from its ledger rows. */
    private static function chargedTo(LeaveApplication $record): ?string
    {
        $draws = $record->ledgerEntries
            ->where('kind', LedgerKind::Application)
            ->map(fn (LeaveLedgerEntry $entry): string => $entry->leave_type->value.' '.bcsub('0', $entry->days, 3));

        if ($draws->isEmpty()) {
            return null;
        }

        return $draws->implode(' · ').($record->status === LeaveApplicationStatus::Cancelled ? ' — given back' : '');
    }

    /** @return list<array{date: string, days: string, without_pay: ?string}> */
    private static function daysByDate(LeaveApplication $record): array
    {
        $rows = [];

        foreach ($record->breakdown as $date => $day) {
            $rows[] = [
                'date' => CarbonImmutable::parse($date)->format('D, M j, Y'),
                'days' => $day['days'],
                'without_pay' => bccomp($day['without_pay'], '0', 3) > 0 ? $day['without_pay'] : null,
            ];
        }

        return $rows;
    }

    private static function isOwn(LeaveApplication $record): bool
    {
        return $record->employee_id === auth()->user()?->employee_id;
    }

    private static function byLine(?string $name, mixed $at): string
    {
        return ($name ?? 'A removed account').' · '.$at->format('M j, Y g:i A');
    }
}
