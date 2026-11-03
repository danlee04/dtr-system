<x-filament-panels::page>
    @include('filament.entry-grid')

    @if ($asOf === null)
        <p role="alert" style="color: var(--danger-600);">
            Set LEAVE_START_DATE in .env to the 1st of the month the system takes over, then come back.
        </p>
    @endif

    <x-filament::section
        class="dtr-grid"
        :heading="$asOf !== null ? 'Balances as of '.$asOf->format('M j, Y') : 'Opening balances'"
        description="Copied once from HR's own records. The system posts every credit after this date."
    >
        <x-slot name="afterHeader">
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
        </x-slot>

        @if ($errors->any())
            <ul role="alert" style="color: var(--danger-600); display: grid; gap: 0.25rem; margin-bottom: 1rem;">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        @if ($employees->isNotEmpty())
            <p class="dtr-grid-count">
                {{ $employees->count() }} {{ str('employee')->plural($employees->count()) }} who earn leave credits · {{ $entered }} with balances typed in
            </p>

            <div class="dtr-grid-wrap">
                <table class="dtr-grid-table">
                    <thead>
                        <tr class="dtr-grid-group">
                            <th scope="col" class="dtr-grid-name"></th>
                            <th scope="colgroup" colspan="{{ count($columns) - 1 }}">Balance</th>
                            <th scope="col" class="dtr-grid-divider">Taken this year</th>
                        </tr>
                        <tr>
                            <th scope="col" class="dtr-grid-name">Employee</th>
                            @foreach ($columns as $code => $heading)
                                <th scope="col" @class(['dtr-grid-divider' => $code === 'FL'])>
                                    {{-- The group heading already says "taken this year". --}}
                                    {{ $code === 'FL' ? 'VL & FL' : $heading }}
                                    @isset($limits[$code])
                                        <span class="dtr-grid-sub">max {{ $limits[$code] }}</span>
                                    @endisset
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            @php($isLocked = in_array($employee->id, $locked, true))
                            <tr wire:key="opening-{{ $employee->id }}">
                                <th scope="row" class="dtr-grid-name">
                                    {{ $employee->full_name }}
                                    <span class="dtr-grid-sub">{{ $employee->employee_number }}</span>
                                    @if ($isLocked)
                                        <span class="dtr-grid-lock">
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::LockClosed" :size="\Filament\Support\Enums\IconSize::Small" />
                                            Locked: credits have posted since. Use an adjustment on the Leave tab.
                                        </span>
                                    @endif
                                </th>
                                @foreach ($columns as $code => $heading)
                                    @php($cell = "rows.{$employee->id}.{$code}")
                                    @if ($isLocked)
                                        <td @class(['dtr-grid-value', 'dtr-grid-divider' => $code === 'FL'])>{{ $rows[$employee->id][$code] ?? '' }}</td>
                                    @else
                                        <td @class(['dtr-grid-divider' => $code === 'FL'])>
                                            <div @class(['fi-input-wrp', 'fi-invalid' => $errors->has($cell)]) style="margin-inline-start: auto; width: fit-content;">
                                                <div class="fi-input-wrp-content-ctn">
                                                    <input
                                                        wire:model="{{ $cell }}"
                                                        wire:dirty.class="dtr-grid-changed"
                                                        class="fi-input dtr-grid-input"
                                                        inputmode="decimal"
                                                        autocomplete="off"
                                                        maxlength="7"
                                                        aria-label="{{ $employee->full_name }}, {{ $heading }}"
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="dtr-grid-bar">
                <x-filament::button wire:click="save">Save opening balances</x-filament::button>
                <x-filament::badge color="warning" wire:dirty>Unsaved changes</x-filament::badge>
                <span class="dtr-grid-hint">
                    Days, up to three decimals; leave a cell blank for none. <strong>Taken this year</strong> is the VL and forced leave already used, for the forced-leave rule on Dec 31.
                </span>
            </div>
        @elseif (filled($officeId))
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedUserGroup"
                heading="No one to enter"
                description="This office has no active employees who earn leave credits."
            />
        @else
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedBuildingOffice2"
                heading="Choose an office"
                description="Its employees who earn leave credits appear here, one row each."
            />
        @endif
    </x-filament::section>
</x-filament-panels::page>
