<?php

namespace App\Filament\Resources\LeaveApplications\Schemas;

use App\Enums\LedgerKind;
use App\Models\LeaveApplication;
use App\Models\LeaveLedgerEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

/**
 * One application: first what the paper does not say (where it stands, what it
 * was charged to, who filed, approved, disapproved or cancelled it), then the
 * form itself as HRM-07 prints it, names and all (HR, 2026-11-23).
 */
class LeaveApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // One row spread across the modal (HR, 2026-11-23): each entry keeps its own width, so a
                // name and its time stay on one line; a cancel or disapproval reason may wrap.
                Flex::make([
                    TextEntry::make('status')
                        ->badge()
                        ->grow(false),
                    TextEntry::make('charged_to')
                        ->label('Charged to')
                        ->state(fn (LeaveApplication $record): ?string => self::chargedTo($record))
                        ->placeholder('Nothing: all without pay')
                        ->grow(false),
                    TextEntry::make('filed')
                        ->label('Filed')
                        ->icon(Heroicon::OutlinedDocumentPlus)
                        ->iconColor('gray')
                        ->state(fn (LeaveApplication $record): string => self::byLine($record->filedBy?->name, $record->created_at))
                        ->grow(false),
                    TextEntry::make('approved')
                        ->label('Approved')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->iconColor('success')
                        ->state(fn (LeaveApplication $record): ?string => $record->approved_at === null ? null : self::byLine($record->approvedBy?->name, $record->approved_at))
                        ->visible(fn (LeaveApplication $record): bool => $record->approved_at !== null)
                        ->grow(false),
                    TextEntry::make('cancelled')
                        ->label('Cancelled')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->iconColor('danger')
                        ->state(fn (LeaveApplication $record): ?string => $record->cancelled_at === null ? null : self::byLine($record->cancelledBy?->name, $record->cancelled_at)." — {$record->cancel_reason}")
                        ->visible(fn (LeaveApplication $record): bool => $record->cancelled_at !== null),
                    TextEntry::make('disapproved')
                        ->label('Disapproved')
                        ->icon(Heroicon::OutlinedHandThumbDown)
                        ->iconColor('danger')
                        ->state(fn (LeaveApplication $record): ?string => $record->disapproved_at === null ? null : self::byLine($record->disapprovedBy?->name, $record->disapproved_at)." — {$record->disapproval_reason}")
                        ->visible(fn (LeaveApplication $record): bool => $record->disapproved_at !== null),
                ])
                    ->from('md')
                    ->alignment(Alignment::Between)
                    ->columnSpanFull(),
                View::make('filament.leave-applications.form')
                    ->viewData(fn (LeaveApplication $record): array => ['html' => view('leave.print', [
                        'application' => $record->load(['employee.office', 'ledgerEntries']),
                        'form' => config('leave.form'),
                        'embedded' => true,
                    ])->render()])
                    ->columnSpanFull(),
            ]);
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

        return $draws->implode(' · ').($record->status->stands() ? '' : ' — given back');
    }

    private static function byLine(?string $name, mixed $at): string
    {
        return ($name ?? 'A removed account').' · '.$at->format('M j, Y g:i A');
    }
}
