<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Auth\Pages\EditProfile;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Everyone's own profile, from the user menu. An account linked to an
 * employee sees who they are on HR's records, beside the photo they may
 * change; everyone has their email and the password. The details are shown,
 * never typed: names, numbers and posts stay HR's business.
 */
class ChangePassword extends EditProfile
{
    protected static ?string $title = 'Profile';

    // A few fields read best in a column, not spread across a wide screen.
    public function getMaxContentWidth(): Width
    {
        return Width::FourExtraLarge;
    }

    public function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = $this->getUser();
        $employee = $user->employee;
        $hasEmployee = $employee !== null;

        return $schema
            ->components([
                // The photo is saved on their employee record. Hidden without one, or saving would make one.
                Section::make()
                    ->relationship('employee')
                    ->visible($hasEmployee)
                    ->schema([
                        Flex::make([
                            // No inline label column to leave blank beside the photo.
                            EmployeeForm::photoField()
                                ->hiddenLabel()
                                ->inlineLabel(false)
                                ->grow(false),
                            View::make('filament.profile.identity')
                                ->viewData(['employee' => $employee]),
                        ])->verticallyAlignCenter(),
                    ]),
                Section::make('Employment')
                    ->description('As HR keeps it. Ask HR to correct anything here.')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->visible($hasEmployee)
                    ->compact()
                    ->divided()
                    ->schema([
                        TextEntry::make('employee.employee_number')
                            ->label('Employee No.'),
                        TextEntry::make('employee.office.name')
                            ->label('Office')
                            ->placeholder('—'),
                        TextEntry::make('employee.position')
                            ->label('Position')
                            ->placeholder('—'),
                        TextEntry::make('employee.employment_status')
                            ->label('Employment status'),
                        TextEntry::make('employee.date_hired')
                            ->label('Date hired')
                            ->date('M j, Y')
                            ->placeholder('—'),
                        TextEntry::make('schedule')
                            ->state(fn (): ?string => ($schedule = $employee?->scheduleOn(CarbonImmutable::today())) ? "{$schedule->name} · {$schedule->hours}" : null)
                            ->placeholder('None fixed; see the duty roster'),
                    ]),
                Section::make('Account')
                    ->icon(Heroicon::OutlinedKey)
                    ->compact()
                    ->schema([
                        TextEntry::make('email'),
                        // An employee may save only a photo; for staff this page is the password.
                        $this->getPasswordFormComponent()->required(! $hasEmployee),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }
}
