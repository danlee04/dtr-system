@php
    use App\Enums\LeaveApplicationStatus;
    use App\Filament\Pages\HabitualTardiness;
    use App\Filament\Pages\LeaveOpeningBalances;
    use App\Filament\Resources\Employees\EmployeeResource;
    use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
    use Filament\Support\Icons\Heroicon;

    $count = $needsALook['count'] ?? 0;

    [$heading, $text, $action, $url] = match ($needsALook['step']) {
        // The Schedules tab: the first relation manager.
        'schedule' => ['No schedule yet', 'Their DTRs cannot be computed until they have a schedule or a roster.', 'Assign a schedule', EmployeeResource::getUrl('view', ['record' => $employee, 'relation' => '0'])],
        'opening-balances' => ['No opening balances yet', 'Their leave balances start from zero until their opening balances are typed in.', 'Open Opening Balances', LeaveOpeningBalances::getUrl()],
        'waiting' => ["{$count} leave ".str('application')->plural($count).' waiting', 'Mark the signed form approved, or cancel it.', 'Open Leave Applications', LeaveApplicationResource::getUrl('index', ['filters' => ['status' => ['value' => LeaveApplicationStatus::Pending->value]]])],
        'lates' => ["Late {$count} times in {$month->format('F')}", 'Ten or more lates in two months of the same half year is habitual tardiness.', 'Open Habitual Tardiness', HabitualTardiness::getUrl()],
    };
@endphp
{{-- The first thing about this employee that HR should look at, after the "due a review" card of HR's reference. --}}
<section class="dtr-callout" aria-labelledby="dtr-needs-a-look">
    <span class="dtr-callout-icon" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedBellAlert" /></span>
    <p class="dtr-callout-eyebrow">Needs a look</p>
    <h2 class="dtr-callout-heading" id="dtr-needs-a-look">{{ $heading }}</h2>
    <p class="dtr-callout-text">{{ $text }}</p>
    <x-filament::button tag="a" :href="$url" size="sm">{{ $action }}</x-filament::button>
</section>
