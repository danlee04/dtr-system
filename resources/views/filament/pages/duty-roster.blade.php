<x-filament-panels::page>
    @include('filament.entry-grid')

    <x-filament::section
        class="dtr-grid"
        :heading="$days !== [] ? $days[0]->format('F Y') : 'Duty roster'"
        description="A schedule code for each day for staff whose duty changes; a blank day follows their fixed schedule."
    >
        <x-slot name="afterHeader">
            <div class="dtr-grid-pickers">
                <label class="dtr-grid-picker">
                    Office
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="officeId">
                            <option value="">All offices</option>
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
            <ul role="alert" class="dtr-grid-errors">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        @if ($days === [])
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendar"
                heading="Choose a month"
                description="The roster covers one calendar month, a column for each day."
            />
        @elseif ($employees->total() === 0)
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedUserGroup"
                heading="No one to roster"
                :description="filled($officeId) ? 'This office has no active employees.' : 'There are no active employees yet.'"
            />
        @else
            <p class="dtr-grid-legend">
                @foreach ($schedules as $schedule)
                    <span><strong>{{ $schedule->code }}</strong>{{ $schedule->hours }}</span>
                @endforeach
                <span><strong>OFF</strong>day off</span>
                <span><strong>blank</strong>the employee's fixed schedule</span>
            </p>

            <div class="dtr-grid-wrap is-full">
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
                                    <span class="dtr-grid-sub">{{ $employee->employee_number }} · {{ $employee->office->name }}</span>
                                </th>
                                @foreach ($days as $day)
                                    @php($cell = "cells.{$employee->id}.{$day->day}")
                                    <td @class(['dtr-grid-weekend' => $day->isWeekend()])>
                                        <div @class(['fi-input-wrp', 'fi-invalid' => $errors->has($cell)])>
                                            <div class="fi-input-wrp-content-ctn">
                                                <input
                                                    wire:model="{{ $cell }}"
                                                    wire:dirty.class="dtr-grid-changed"
                                                    @class(['fi-input dtr-grid-input is-code', 'dtr-grid-changed' => isset($unsaved["{$employee->id}.{$day->day}"])])
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

            <x-filament::pagination
                :paginator="$employees"
                :page-options="$pageSizes"
                current-page-option-property="perPage"
                class="dtr-grid-after"
            />

            <div class="dtr-grid-bar">
                <x-filament::button wire:click="save">Save roster</x-filament::button>
                {{-- Counted on the server too: a cell typed on another page is off the screen, where the browser no longer marks it. --}}
                @if ($unsaved !== [])
                    <x-filament::badge color="warning">{{ count($unsaved) }} unsaved {{ str('change')->plural(count($unsaved)) }}</x-filament::badge>
                @else
                    <x-filament::badge color="warning" wire:dirty>Unsaved changes</x-filament::badge>
                @endif
                <span class="dtr-grid-hint">Type a code from the list above, or OFF for a day off. Save keeps every page and office, not only the one on screen.</span>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
