<x-filament-panels::page>
    @if ($asOf === null)
        <p role="alert" style="color: var(--danger-600);">
            Set LEAVE_START_DATE in .env to the 1st of the month the system takes over, then come back.
        </p>
    @else
        <p>Balances as of {{ $asOf->format('M j, Y') }}, from HR's own records. The system posts every credit after that.</p>
    @endif

    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="officeId" aria-label="Office">
                <option value="">Choose an office</option>
                @foreach ($offices as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    @if ($errors->any())
        <ul role="alert" style="color: var(--danger-600); display: grid; gap: 0.25rem;">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @if ($employees->isNotEmpty())
        <x-filament::section>
            <p style="margin-bottom: 1rem;">
                Days, up to three decimals. Leave a cell blank for none. <strong>VL/FL taken this year</strong> is the VL and forced leave already used this year, for the forced-leave rule on 31 December.
            </p>

            {{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
            <div style="overflow-x: auto; background-color: inherit;">
                <table style="border-collapse: collapse; background-color: inherit;">
                    <thead style="background-color: inherit;">
                        <tr style="background-color: inherit;">
                            <th scope="col" style="position: sticky; left: 0; z-index: 1; background-color: inherit; text-align: start; padding: 0.25rem 0.5rem;">Employee</th>
                            @foreach ($columns as $heading)
                                <th scope="col" style="padding: 0.25rem 0.5rem; font-weight: 500; text-align: center;">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody style="background-color: inherit;">
                        @foreach ($employees as $employee)
                            <tr style="background-color: inherit;">
                                <th scope="row" style="position: sticky; left: 0; z-index: 1; background-color: inherit; text-align: start; white-space: nowrap; padding: 0.25rem 0.5rem;">{{ $employee->full_name }}</th>
                                @foreach ($columns as $code => $heading)
                                    @php($cell = "rows.{$employee->id}.{$code}")
                                    <td style="padding: 0.125rem;">
                                        <div @class(['fi-input-wrp', 'fi-invalid' => $errors->has($cell)])>
                                            <div class="fi-input-wrp-content-ctn">
                                                <input
                                                    wire:model="{{ $cell }}"
                                                    class="fi-input"
                                                    inputmode="decimal"
                                                    maxlength="7"
                                                    aria-label="{{ $employee->full_name }}, {{ $heading }}"
                                                    style="width: 6rem; padding-inline: 0.5rem; text-align: end;"
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
                <x-filament::button wire:click="save">Save opening balances</x-filament::button>
                <span wire:dirty>Unsaved changes</span>
            </div>
        </x-filament::section>
    @elseif (filled($officeId))
        <p>This office has no active employees who earn leave credits.</p>
    @endif
</x-filament-panels::page>
