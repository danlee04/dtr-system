<x-filament-panels::page>
    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="officeId" aria-label="Office">
                <option value="">Choose an office</option>
                @foreach ($offices as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input type="month" wire:model.live="month" aria-label="Month" />
        </x-filament::input.wrapper>
    </div>

    @if ($errors->any())
        <ul role="alert" style="color: var(--danger-600); display: grid; gap: 0.25rem;">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @if ($employees->isNotEmpty() && $days !== [])
        <x-filament::section>
            <p style="display: flex; flex-wrap: wrap; gap: 0.25rem 1rem; margin-bottom: 1rem;">
                @foreach ($schedules as $schedule)
                    <span><strong>{{ $schedule->code }}</strong> {{ $schedule->hours }}</span>
                @endforeach
                <span><strong>OFF</strong> day off</span>
                <span><strong>blank</strong> the employee's fixed schedule</span>
            </p>

            {{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
            <div style="overflow-x: auto; background-color: inherit;">
                <table style="border-collapse: collapse; background-color: inherit;">
                    <thead style="background-color: inherit;">
                        <tr style="background-color: inherit;">
                            <th scope="col" style="position: sticky; left: 0; z-index: 1; background-color: inherit; text-align: start; padding: 0.25rem 0.5rem;">Employee</th>
                            @foreach ($days as $day)
                                <th scope="col" @style([
                                    'padding: 0.25rem; font-weight: 500; text-align: center;',
                                    'background-color: rgba(127, 127, 127, 0.08);' => $day->isWeekend(),
                                ])>
                                    {{ $day->format('j') }}<br>
                                    <span style="font-size: 0.75rem; opacity: 0.7;">{{ mb_substr($day->format('D'), 0, 2) }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody style="background-color: inherit;">
                        @foreach ($employees as $employee)
                            <tr style="background-color: inherit;">
                                <th scope="row" style="position: sticky; left: 0; z-index: 1; background-color: inherit; text-align: start; white-space: nowrap; padding: 0.25rem 0.5rem;">{{ $employee->full_name }}</th>
                                @foreach ($days as $day)
                                    @php($cell = "cells.{$employee->id}.{$day->day}")
                                    <td @style([
                                        'padding: 0.125rem;',
                                        'background-color: rgba(127, 127, 127, 0.08);' => $day->isWeekend(),
                                    ])>
                                        <div @class(['fi-input-wrp', 'fi-invalid' => $errors->has($cell)])>
                                            <div class="fi-input-wrp-content-ctn">
                                                <input
                                                    wire:model="{{ $cell }}"
                                                    class="fi-input"
                                                    maxlength="10"
                                                    aria-label="{{ $employee->full_name }}, {{ $day->format('M j') }}"
                                                    style="width: 3rem; padding-inline: 0.25rem; text-align: center; text-transform: uppercase;"
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

            <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem;">
                <x-filament::button wire:click="save">Save roster</x-filament::button>
                <span wire:dirty>Unsaved changes</span>
            </div>
        </x-filament::section>
    @elseif (filled($officeId))
        <p>This office has no active employees.</p>
    @endif
</x-filament-panels::page>
