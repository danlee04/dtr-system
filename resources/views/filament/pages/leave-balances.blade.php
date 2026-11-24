@use('App\Enums\LeaveType')
<x-filament-panels::page>
    <x-filament::section class="dtr-grid" :heading="$monthName"
        description="Each balance at the end of the month. Where the month took from it: in grey the balance before, in red what it took (DTR deductions, leave, forced leave, lapse and adjustments).">
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
                        <x-filament::input.select wire:model.live="month">
                            @foreach ($this->months() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        </x-slot>

        @if ($employees->total() > 0)
            <p class="dtr-grid-count">{{ $employees->total() }} {{ str('employee')->plural($employees->total()) }} who earn leave credits</p>

            <div class="dtr-grid-wrap is-full">
                <table class="dtr-grid-table">
                    <thead>
                        <tr>
                            <th scope="col" class="dtr-grid-name">Employee</th>
                            @foreach (LeaveType::tracked() as $type)
                                <th scope="col" class="dtr-grid-divider">{{ $type->getLabel() }}<span class="dtr-grid-sub">days</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            <tr wire:key="balances-{{ $employee->id }}">
                                <th scope="row" class="dtr-grid-name">
                                    {{ $employee->full_name }}
                                    <span class="dtr-grid-sub">{{ $employee->employee_number }} · {{ $employee->office->name }}</span>
                                </th>
                                @foreach (LeaveType::tracked() as $type)
                                    @php($cell = $figures[$employee->id][$type->value])
                                    <td class="dtr-grid-value dtr-grid-divider">
                                        {{-- Where the month took something, a sum: the balance before it, what it took (HR, 2026-11-20), then the month's end (HR, 2026-11-23). --}}
                                        @if (bccomp($cell['taken'], '0', 3) > 0)
                                            <span class="dtr-grid-sum">
                                                <span class="dtr-grid-before"><span class="fi-sr-only">Before: </span>{{ bcadd($cell['balance'], $cell['taken'], 3) }}</span>
                                                <span class="dtr-grid-taken">&minus;{{ $cell['taken'] }}</span>
                                                <span class="dtr-grid-total"><span class="fi-sr-only">End of month: </span>{{ $cell['balance'] }}</span>
                                            </span>
                                        @else
                                            {{ $cell['balance'] }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-filament::pagination :paginator="$employees" :page-options="$pageSizes" current-page-option-property="perPage"
                class="dtr-grid-after" />

            <p class="dtr-grid-hint dtr-grid-after">
                A leave counts in the month of its first date, a monthly credit or a DTR in the month it is for, even when
                posted later, so October's DTR marked final in November is October's.
            </p>
        @else
            <x-filament::empty-state :contained="false" :icon="\Filament\Support\Icons\Heroicon::OutlinedScale"
                heading="No one to show" description="No staff who earn leave credits in this office." />
        @endif
    </x-filament::section>
</x-filament-panels::page>
