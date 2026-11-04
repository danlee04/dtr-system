<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\Attendance\ComputedDtr;
use App\Services\EmployeeOverview;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;

/**
 * One employee, laid out after HR's reference: who they are in the heading,
 * then the Overview (cards, details, what needs a look) beside the Schedules
 * and Leave tabs, which need a page of their own. Editing opens in a modal.
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

    /** Their photo, or their initials without one, beside their name. Both views escape what they print. */
    public function getHeading(): Htmlable
    {
        return new HtmlString(view('filament.employees.heading', [
            'employee' => $this->record,
            'avatar' => $this->record->photoUrl() ?? $this->initialsAvatar(),
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
        return 'Overview';
    }

    public function getContentTabIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedIdentification;
    }

    /**
     * What the Overview's cards and callout show, worked out once a request.
     * Computed, so the browser cannot call it.
     *
     * @return array{earns: bool, month: CarbonImmutable, lastMonth: ComputedDtr|null, balances: array<string, string>, schedule: Schedule|null, needsALook: array{step: string, count?: int}|null}
     */
    #[Computed]
    public function overview(): array
    {
        return app(EmployeeOverview::class)->for($this->record, CarbonImmutable::today());
    }

    protected function getHeaderActions(): array
    {
        return [
            // The same width as New employee.
            EditAction::make()
                ->modalWidth(Width::SixExtraLarge),
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
