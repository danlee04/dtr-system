@php
    use Filament\Support\Icons\Heroicon;
@endphp
{{-- Who the employee is at a glance: status first, then what HR looks them up by. Each chip stays on one line. --}}
<span style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
    <x-filament::badge :color="$status['color']">{{ $status['label'] }}</x-filament::badge>
    <x-filament::badge color="gray">{{ $employee->employment_status->getLabel() }}</x-filament::badge>
    @if (filled($employee->position))
        <x-filament::badge color="gray" :icon="Heroicon::OutlinedBriefcase">{{ $employee->position }}</x-filament::badge>
    @endif
    @if ($employee->office)
        <x-filament::badge color="gray" :icon="Heroicon::OutlinedBuildingOffice2">{{ $employee->office->name }}</x-filament::badge>
    @endif
    <x-filament::badge color="gray" :icon="Heroicon::OutlinedIdentification">{{ $employee->employee_number }}</x-filament::badge>
</span>
