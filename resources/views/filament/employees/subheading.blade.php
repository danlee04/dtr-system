@php
    use Filament\Support\Icons\Heroicon;
@endphp
{{-- Who the employee is at a glance: their position and office, then their status and number as chips. Each chip stays on one line. --}}
<span class="dtr-person-chips">
    @if (filled($employee->position) || $employee->office)
        <span class="dtr-person-role">{{ collect([$employee->position, $employee->office?->name])->filter()->implode(' · ') }}</span>
    @endif
    <x-filament::badge :color="$status['color']">{{ $status['label'] }}</x-filament::badge>
    <x-filament::badge color="gray">{{ $employee->employment_status->getLabel() }}</x-filament::badge>
    <x-filament::badge color="gray" :icon="Heroicon::OutlinedIdentification">{{ $employee->employee_number }}</x-filament::badge>
</span>
