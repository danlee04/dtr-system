<x-filament-panels::page>
    @include('filament.entry-grid')

    <x-filament::section
        class="dtr-grid"
        :heading="$monthName ?? 'Monthly summary'"
        description="Late, undertime and absences of permanent and co-terminous staff, from their uploaded DTRs."
    >
        <x-slot name="afterHeader">
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
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
            <ul role="alert" style="color: var(--danger-600); display: grid; gap: 0.25rem; margin-bottom: 1rem;">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        @if ($rows->total() > 0)
            <p class="dtr-grid-count">
                {{ $rows->total() }} {{ str('employee')->plural($rows->total()) }} who earn leave credits · {{ $withDtr }} with a DTR for the month
            </p>

            <div class="dtr-grid-wrap is-full">
                <table class="dtr-grid-table">
                    <thead>
                        <tr class="dtr-grid-group">
                            <th scope="col" class="dtr-grid-name"></th>
                            <th scope="col"></th>
                            <th scope="colgroup" colspan="2" class="dtr-grid-divider">Late</th>
                            <th scope="colgroup" colspan="2" class="dtr-grid-divider">Undertime</th>
                            <th scope="col" class="dtr-grid-divider"></th>
                            <th scope="col"></th>
                            <th scope="col" class="dtr-grid-divider"></th>
                        </tr>
                        <tr>
                            <th scope="col" class="dtr-grid-name">Employee</th>
                            <th scope="col" style="text-align: start;">Office</th>
                            <th scope="col" class="dtr-grid-divider">Times</th>
                            <th scope="col">Minutes</th>
                            <th scope="col" class="dtr-grid-divider">Times</th>
                            <th scope="col">Minutes</th>
                            <th scope="col" class="dtr-grid-divider">Absent<span class="dtr-grid-sub">days</span></th>
                            <th scope="col">Equivalent<span class="dtr-grid-sub">days of leave</span></th>
                            <th scope="col" class="dtr-grid-divider" style="text-align: start;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as ['employee' => $employee, 'dtr' => $dtr, 'notes' => $notes])
                            <tr wire:key="summary-{{ $employee->id }}" @style(['color: var(--grid-muted)' => $dtr === null])>
                                <th scope="row" class="dtr-grid-name">
                                    {{ $employee->full_name }}
                                    <span class="dtr-grid-sub">{{ $employee->employee_number }}</span>
                                </th>
                                <td>{{ $employee->office->name }}</td>
                                <td class="dtr-grid-value dtr-grid-divider">{{ $dtr?->lateCount ?? '—' }}</td>
                                <td class="dtr-grid-value">{{ $dtr?->late ?? '—' }}</td>
                                <td class="dtr-grid-value dtr-grid-divider">{{ $dtr?->undertimeCount ?? '—' }}</td>
                                <td class="dtr-grid-value">{{ $dtr?->undertime ?? '—' }}</td>
                                <td class="dtr-grid-value dtr-grid-divider">{{ $dtr?->absent ?? '—' }}</td>
                                <td class="dtr-grid-value">{{ $dtr?->dayEquivalent ?? '—' }}</td>
                                <td class="dtr-grid-divider">
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                        @foreach ($notes as $note)
                                            <x-filament::badge color="warning">{{ $note }}</x-filament::badge>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-filament::pagination
                :paginator="$rows"
                :page-options="$pageSizes"
                current-page-option-property="perPage"
                style="margin-top: 0.75rem;"
            />

            <p class="dtr-grid-hint" style="margin-top: 0.75rem;">
                The equivalent is late and undertime together, as days of leave by the CSC table. A DTR that matched no employee is left out until you choose its employee on DTR Uploads.
            </p>
        @elseif ($monthName === null)
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendar"
                heading="Choose a month"
                description="The summary covers one calendar month."
            />
        @else
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedUserGroup"
                heading="No one to summarize"
                :description="filled($officeId) ? 'This office has no active employees who earn leave credits.' : 'No active employee earns leave credits yet.'"
            />
        @endif
    </x-filament::section>
</x-filament-panels::page>
