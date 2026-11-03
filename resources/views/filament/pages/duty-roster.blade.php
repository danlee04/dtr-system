<x-filament-panels::page>
    @include('filament.entry-grid')

    <x-filament::section
        class="dtr-grid"
        :heading="$days !== [] ? $days[0]->format('F Y') : 'Duty roster'"
        description="A schedule code for each day, for staff whose duty changes day to day. A blank day follows the employee's fixed schedule."
    >
        <x-slot name="afterHeader">
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                <label class="dtr-grid-picker">
                    Office
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="officeId">
                            <option value="">Choose an office</option>
                            @foreach ($offices as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="dtr-grid-picker">
                    Month
                    <x-filament::input.wrapper>
                        <x-filament::input type="month" wire:model.live="month" />
                    </x-filament::input.wrapper>
                </label>
            </div>
        </x-slot>

        @if ($errors->any())
            <ul role="alert" style="color: var(--danger-600); display: grid; gap: 0.25rem; margin-bottom: 1rem;">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        @if ($employees->isNotEmpty() && $days !== [])
            <p class="dtr-grid-legend">
                @foreach ($schedules as $schedule)
                    <span><strong>{{ $schedule->code }}</strong>{{ $schedule->hours }}</span>
                @endforeach
                <span><strong>OFF</strong>day off</span>
                <span><strong>blank</strong>the employee's fixed schedule</span>
            </p>

            <div class="dtr-grid-wrap">
                <table class="dtr-grid-table is-dense is-centered">
                    <thead>
                        <tr>
                            <th scope="col" class="dtr-grid-name">Employee</th>
                            @foreach ($days as $day)
                                <th scope="col" @class(['dtr-grid-weekend' => $day->isWeekend()])>
                                    {{ $day->format('j') }}
                                    <span class="dtr-grid-sub">{{ mb_substr($day->format('D'), 0, 2) }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            <tr wire:key="roster-{{ $employee->id }}">
                                <th scope="row" class="dtr-grid-name">
                                    {{ $employee->full_name }}
                                    <span class="dtr-grid-sub">{{ $employee->employee_number }}</span>
                                </th>
                                @foreach ($days as $day)
                                    @php($cell = "cells.{$employee->id}.{$day->day}")
                                    <td @class(['dtr-grid-weekend' => $day->isWeekend()])>
                                        <div @class(['fi-input-wrp', 'fi-invalid' => $errors->has($cell)]) style="margin-inline: auto; width: fit-content;">
                                            <div class="fi-input-wrp-content-ctn">
                                                <input
                                                    wire:model="{{ $cell }}"
                                                    wire:dirty.class="dtr-grid-changed"
                                                    class="fi-input dtr-grid-input is-code"
                                                    autocomplete="off"
                                                    maxlength="10"
                                                    aria-label="{{ $employee->full_name }}, {{ $day->format('M j') }}"
                                                />
                                            </div>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="dtr-grid-bar">
                <x-filament::button wire:click="save">Save roster</x-filament::button>
                <x-filament::badge color="warning" wire:dirty>Unsaved changes</x-filament::badge>
                <span class="dtr-grid-hint">Type a code from the list above, or OFF for a day off.</span>
            </div>
        @elseif (filled($officeId))
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedUserGroup"
                heading="No one to roster"
                description="This office has no active employees."
            />
        @else
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedBuildingOffice2"
                heading="Choose an office"
                description="Its employees appear here, one row each, with a column for every day of the month."
            />
        @endif
    </x-filament::section>
</x-filament-panels::page>
