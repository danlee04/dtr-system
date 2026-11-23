@php
    use Filament\Support\Icons\Heroicon;
@endphp
{{--
    Under the name, after HR's reference (2026-11-23): the office, where they stand, and their
    number, as chips. The position and employment status are in Job Details. Each chip stays on one line.
--}}
<span class="dtr-person-chips">
    @if ($employee->office)
        <span class="dtr-chip is-office">
            <x-filament::icon :icon="Heroicon::Briefcase" class="dtr-chip-icon" />
            {{ $employee->office->name }}
        </span>
    @endif
    <span class="dtr-chip is-{{ $status['color'] }}">
        <span class="dtr-chip-dot" aria-hidden="true"></span>
        {{ $status['label'] }}
    </span>
    <span class="dtr-chip">
        <x-filament::icon :icon="Heroicon::OutlinedIdentification" class="dtr-chip-icon" />
        <span class="fi-sr-only">Employee No. </span>{{ $employee->employee_number }}
    </span>
</span>
