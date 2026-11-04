@php
    use App\Services\Attendance\HabitualTardiness;
    use Filament\Support\Icons\Heroicon;
@endphp
<x-filament-panels::page>
    @include('filament.entry-grid')

    <x-filament::section
        class="dtr-grid"
        :heading="$label"
        description="Late ten or more times in a month, in two months of the same half year or in two months in a row, is habitual tardiness under CSC rules."
    >
        <x-slot name="afterHeader">
            <div class="dtr-grid-pickers">
                <label class="dtr-grid-picker">
                    Year
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="year">
                            @foreach ($years as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="dtr-grid-picker">
                    Half
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="half">
                            <option value="1">January–June</option>
                            <option value="2">July–December</option>
                        </x-filament::input.select>
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

        @if ($rows !== [])
            <p class="dtr-grid-count">
                {{ count($rows) }} {{ str('employee')->plural(count($rows)) }} late 10 or more times in a month · {{ $habitualCount }} habitual
            </p>

            <div class="dtr-grid-wrap is-full">
                <table class="dtr-grid-table">
                    <thead>
                        <tr>
                            <th scope="col" class="dtr-grid-name">Employee</th>
                            @foreach ($months as $month)
                                <th scope="col">{{ $month->format('M') }}<span class="dtr-grid-sub">lates</span></th>
                            @endforeach
                            <th scope="col" class="dtr-grid-divider dtr-grid-start">Habitual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as ['employee' => $employee, 'lates' => $lates, 'habitual' => $habitual])
                            <tr wire:key="habitual-{{ $employee->id }}">
                                <th scope="row" class="dtr-grid-name">
                                    {{ $employee->full_name }}
                                    <span class="dtr-grid-sub">{{ $employee->employee_number }} · {{ $employee->office->name }}</span>
                                </th>
                                @foreach ($lates as $count)
                                    <td class="dtr-grid-value">
                                        @if ($count === null)
                                            —
                                        @elseif ($count >= HabitualTardiness::LATES)
                                            <x-filament::badge color="warning">{{ $count }}</x-filament::badge>
                                        @else
                                            {{ $count }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="dtr-grid-divider">
                                    @if ($habitual !== [])
                                        <x-filament::badge color="danger">Habitual</x-filament::badge>
                                        <span class="dtr-grid-sub">{{ HabitualTardiness::monthNames($habitual) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="dtr-grid-hint dtr-grid-after">
                Every late counts once: late in the morning and again after lunch is two. Only staff who earn leave credits are counted, from their uploaded DTRs; the figures refresh every 10 minutes.
            </p>
        @else
            <x-filament::empty-state
                :contained="false"
                :icon="Heroicon::OutlinedCheckCircle"
                heading="No one late 10 or more times"
                :description="'No one was late ten or more times in a month in '.$label.', from the DTRs uploaded.'"
            />
        @endif
    </x-filament::section>
</x-filament-panels::page>
