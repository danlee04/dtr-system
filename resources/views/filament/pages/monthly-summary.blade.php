@use('App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist')
<x-filament-panels::page>
    @include('filament.entry-grid')

    <x-filament::section class="dtr-grid" :heading="$monthName ?? 'Monthly summary'"
        description="Late, undertime and absences of permanent and co-terminous staff, from their uploaded DTRs.">
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

        {{-- Out of the header, which a third control squeezes on a tablet, and shown even when nothing matches, so it can be cleared. --}}
        @if ($monthName !== null)
            <label class="dtr-grid-picker dtr-grid-count">
                <x-filament::input.checkbox wire:model.live="withoutPayOnly" />
                Without pay only
            </label>
        @endif

        @if ($rows->total() > 0)
            <p class="dtr-grid-count">
                {{ $rows->total() }} {{ str('employee')->plural($rows->total()) }} who earn leave credits ·
                {{ $withDtr }} with a DTR for the month
            </p>

            <div class="dtr-grid-wrap is-full">
                <table class="dtr-grid-table">
                    {{-- The office under the name and the times under the hours, as on Duty Roster, so the report fits a laptop or a tablet. --}}
                    <thead>
                        <tr>
                            <th scope="col" class="dtr-grid-name">Employee</th>
                            <th scope="col" class="dtr-grid-divider">Late</th>
                            <th scope="col" class="dtr-grid-divider">Undertime</th>
                            <th scope="col" class="dtr-grid-divider">Absent<span class="dtr-grid-sub">days</span>
                            </th>
                            <th scope="col">Equivalent<span class="dtr-grid-sub">days of leave</span></th>
                            <th scope="col" class="dtr-grid-divider">Without pay<span class="dtr-grid-sub">days</span></th>
                            <th scope="col" class="dtr-grid-divider dtr-grid-start">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as ['employee' => $employee, 'dtr' => $dtr, 'notes' => $notes, 'withoutPay' => $withoutPay])
                            <tr wire:key="summary-{{ $employee->id }}" @class(['is-muted' => $dtr === null])>
                                <th scope="row" class="dtr-grid-name">
                                    {{ $employee->full_name }}
                                    <span class="dtr-grid-sub">{{ $employee->employee_number }} · {{ $employee->office->name }}</span>
                                </th>
                                @foreach ([[$dtr?->late, $dtr?->lateCount], [$dtr?->undertime, $dtr?->undertimeCount]] as [$minutes, $times])
                                    <td class="dtr-grid-value dtr-grid-divider">
                                        @if ($dtr === null)
                                            —
                                        @else
                                            {{ DtrUploadInfolist::hours($minutes) }}
                                            <span class="dtr-grid-sub">{{ $times }} {{ str('time')->plural($times) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="dtr-grid-value dtr-grid-divider">{{ $dtr?->absent ?? '—' }}</td>
                                <td class="dtr-grid-value">{{ $dtr?->dayEquivalent ?? '—' }}</td>
                                {{-- One column on screen, so the report still fits a tablet; the Excel has the three. --}}
                                <td class="dtr-grid-value dtr-grid-divider">
                                    @if ($dtr === null && bccomp($withoutPay['leave'], '0', 3) === 0)
                                        —
                                    @else
                                        {{ $withoutPay['total'] }}
                                        @if (bccomp($withoutPay['total'], '0', 3) > 0)
                                            <span class="dtr-grid-sub">DTR {{ $withoutPay['dtr'] }} · leave {{ $withoutPay['leave'] }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="dtr-grid-divider">
                                    <div class="dtr-grid-badges">
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

            <x-filament::pagination :paginator="$rows" :page-options="$pageSizes" current-page-option-property="perPage"
                class="dtr-grid-after" />

            <p class="dtr-grid-hint dtr-grid-after">
                The equivalent is late and undertime together, as days of leave by the CSC table. A DTR that matched no
                employee is left out until you choose its employee on DTR Uploads.
            </p>
        @elseif ($monthName === null)
            <x-filament::empty-state :contained="false" :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendar" heading="Choose a month"
                description="The summary covers one calendar month." />
        @else
            <x-filament::empty-state :contained="false" :icon="\Filament\Support\Icons\Heroicon::OutlinedUserGroup" heading="No one to summarize"
                :description="filled($officeId)
                    ? 'This office has no active employees who earn leave credits.'
                    : 'No active employee earns leave credits yet.'" />
        @endif
    </x-filament::section>
</x-filament-panels::page>
