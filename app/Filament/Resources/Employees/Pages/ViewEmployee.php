<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Widgets\EmployeeLeaveBalances;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * One employee: who they are in the heading, their balances above the tabs,
 * and their details beside the Schedules and Leave tabs, which need a page of
 * their own. Editing opens in a modal over it.
 *
 * @property Employee $record
 */
class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return $this->record->full_name;
    }

    /** Their initials beside their name. Both views escape what they print. */
    public function getHeading(): Htmlable
    {
        return new HtmlString(view('filament.employees.heading', [
            'employee' => $this->record,
            'avatar' => $this->initialsAvatar(),
        ])->render());
    }

    /** Status, employment status, position, office and number, as chips. */
    public function getSubheading(): Htmlable
    {
        return new HtmlString(view('filament.employees.subheading', [
            'employee' => $this->record,
            'status' => $this->status(),
        ])->render());
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Details';
    }

    public function getContentTabIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedIdentification;
    }

    /** Only for those who earn leave: the rest would show four zeroes. */
    protected function getHeaderWidgets(): array
    {
        return $this->record->employment_status->earnsLeaveCredits()
            ? [EmployeeLeaveBalances::class]
            : [];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            // Outlined, so Edit is the one that stands out.
            DeleteAction::make()->outlined(),
            RestoreAction::make()->outlined(),
        ];
    }

    /**
     * Where the employee stands, worded so it never rests on the colour alone.
     *
     * @return array{label: string, color: string}
     */
    private function status(): array
    {
        $employee = $this->record;

        return match (true) {
            $employee->trashed() => ['label' => 'Deleted', 'color' => 'danger'],
            $employee->date_separated?->lt(today()) === true => ['label' => 'Separated '.$employee->date_separated->format('M j, Y'), 'color' => 'danger'],
            ! $employee->is_active => ['label' => 'Inactive', 'color' => 'gray'],
            default => ['label' => 'Active', 'color' => 'success'],
        };
    }

    /**
     * Their initials as a small SVG, built here: no photo to show, and no
     * name sent to an avatar service.
     */
    private function initialsAvatar(): string
    {
        $initials = mb_strtoupper(mb_substr($this->record->first_name, 0, 1).mb_substr($this->record->last_name, 0, 1));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#fef3c7"/>'
            .'<text x="32" y="32" dy="0.35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" font-weight="600" fill="#92400e">'
            .htmlspecialchars($initials, ENT_XML1)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
