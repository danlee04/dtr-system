<?php

namespace App\Filament\Resources\LeaveApplications\Schemas;

use App\Models\LeaveApplication;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LeaveApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('employee.full_name')
                    ->label('Employee'),
                TextEntry::make('leave_type')
                    ->label('Leave type'),
                TextEntry::make('dates')
                    ->label('Dates')
                    ->state(fn (LeaveApplication $record): string => $record->dates()),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('days')
                    ->label('Days'),
                TextEntry::make('days_with_pay')
                    ->label('With pay'),
                TextEntry::make('days_without_pay')
                    ->label('Without pay'),
                TextEntry::make('detail')
                    ->label('Details')
                    ->placeholder('—'),
                TextEntry::make('detail_text')
                    ->label('Specified')
                    ->placeholder('—'),
                IconEntry::make('commutation_requested')
                    ->label('Commutation requested')
                    ->boolean(),
                TextEntry::make('breakdown')
                    ->label('Days by date')
                    ->state(fn (LeaveApplication $record): array => self::breakdownLines($record))
                    ->listWithLineBreaks()
                    ->columnSpanFull(),
                TextEntry::make('filed')
                    ->label('Filed')
                    ->state(fn (LeaveApplication $record): string => self::byLine($record->filedBy?->name, $record->created_at)),
                TextEntry::make('approved')
                    ->label('Approved')
                    ->state(fn (LeaveApplication $record): ?string => $record->approved_at === null ? null : self::byLine($record->approvedBy?->name, $record->approved_at))
                    ->placeholder('—'),
                TextEntry::make('cancelled')
                    ->label('Cancelled')
                    ->state(fn (LeaveApplication $record): ?string => $record->cancelled_at === null ? null : self::byLine($record->cancelledBy?->name, $record->cancelled_at).": {$record->cancel_reason}")
                    ->placeholder('—'),
            ]);
    }

    /** @return list<string> "Tue, Nov 3, 2026: 1.000 (0.250 without pay)" */
    private static function breakdownLines(LeaveApplication $record): array
    {
        $lines = [];

        foreach ($record->breakdown as $date => $day) {
            $line = CarbonImmutable::parse($date)->format('D, M j, Y').": {$day['days']}";

            if (bccomp($day['without_pay'], '0', 3) > 0) {
                $line .= " ({$day['without_pay']} without pay)";
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private static function byLine(?string $name, mixed $at): string
    {
        return ($name ?? 'A removed account').' on '.$at->format('M j, Y g:i A');
    }
}
