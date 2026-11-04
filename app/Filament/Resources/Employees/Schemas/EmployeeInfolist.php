<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The employee page's Overview tab, after HR's reference: cards of last
 * month, the leave balances and the schedule; the job details as rows; and
 * beside them what needs a look and the name the DTRs are matched to.
 */
class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.employees.cards')
                    ->viewData(fn (ViewEmployee $livewire): array => $livewire->overview)
                    ->columnSpanFull(),
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Job details')
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->compact()
                            ->divided()
                            ->inlineLabel()
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                TextEntry::make('employee_number')
                                    ->label('Employee No.'),
                                TextEntry::make('office.name')
                                    ->label('Office'),
                                TextEntry::make('position')
                                    ->placeholder('—'),
                                TextEntry::make('employment_status'),
                                // How long they have served, to the day they left if they have.
                                TextEntry::make('date_hired')
                                    ->formatStateUsing(fn (CarbonInterface $state, Employee $record): string => $state->format('M j, Y').' · '.$state->longAbsoluteDiffForHumans($record->date_separated ?? today(), 2))
                                    ->placeholder('—'),
                                TextEntry::make('date_separated')
                                    ->date('M j, Y')
                                    ->placeholder('—'),
                                // Blank means their punches cannot be matched yet, so it says so.
                                TextEntry::make('biometric_id')
                                    ->label('Biometric ID')
                                    ->placeholder('Not set'),
                            ]),
                        Group::make([
                            View::make('filament.employees.needs-a-look')
                                ->viewData(fn (ViewEmployee $livewire): array => [...$livewire->overview, 'employee' => $livewire->record])
                                ->visible(fn (ViewEmployee $livewire): bool => $livewire->overview['needsALook'] !== null),
                            Section::make('Name')
                                ->description('DTR uploads are matched to this name.')
                                ->icon(Heroicon::OutlinedUser)
                                ->compact()
                                ->divided()
                                ->inlineLabel()
                                ->schema([
                                    TextEntry::make('last_name'),
                                    TextEntry::make('first_name'),
                                    TextEntry::make('middle_name')
                                        ->placeholder('—'),
                                    TextEntry::make('suffix')
                                        ->placeholder('—'),
                                ]),
                        ]),
                    ]),
            ]);
    }
}
