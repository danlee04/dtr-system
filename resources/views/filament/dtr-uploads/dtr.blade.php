@php
    use App\Enums\DtrSlot;

    // Only HR's view registers these; on My DTR there are none, for anyone.
    $fill = $getAction('fillDay');
    $addException = $getAction('addDayException');
    $editException = $getAction('editDayException');
@endphp
@include('filament.entry-grid')

{{-- One DTR: whose it is, the month in cards, then every date as on the paper form. Everything printed here is escaped. --}}
<div class="dtr-grid dtr-record">
    {{-- On My DTR the page's own heading says whose and when. --}}
    @unless ($mine)
        <div class="dtr-record-head">
            @if ($upload->employee)
                <x-filament::avatar :src="$upload->employee->avatarUrl()" alt="" size="lg" />
            @endif
            <div>
                @if ($upload->employee)
                    <p class="dtr-record-line">{{ $upload->employee->employee_number }} · {{ $upload->employee->office?->name ?? 'No office' }}</p>
                @endif
                @if ($schedule)
                    <p class="dtr-record-sub">{{ $schedule }}</p>
                @endif
                <p class="dtr-record-sub">{{ $printed }}</p>
            </div>
        </div>
    @endunless

    @if ($notComputed)
        <p class="dtr-record-warning">{{ $notComputed }}</p>
    @else
        <dl class="dtr-profile-cards dtr-record-cards">
            @foreach ($cards as $card)
                <div @class(['dtr-profile-card', $card['tone']])>
                    <span class="dtr-profile-card-icon" aria-hidden="true"><x-filament::icon :icon="$card['icon']" /></span>
                    <dt class="dtr-profile-card-label">{{ $card['label'] }}</dt>
                    <dd class="dtr-profile-card-value">{{ $card['value'] }}</dd>
                    <dd class="dtr-profile-card-detail">{{ $card['detail'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if ($final)
        <p class="dtr-record-sub">{{ $final['line'] }}</p>
        @if ($final['changed'])
            <p class="dtr-record-warning">{{ $final['changed'] }}</p>
        @endif
    @endif

    <h3 class="dtr-record-heading">Days</h3>
    <p class="dtr-record-desc">
        As the IT Unit printed it.
        @if ($fill && ! $notComputed)
            Click <strong>Add</strong>, or a time the device got wrong, to copy in the time written on the DTR and signed by the supervisor.
        @endif
    </p>

    <div class="dtr-grid-wrap is-full">
        <table class="dtr-grid-table dtr-record-days">
            <thead>
                <tr class="dtr-grid-group">
                    <th></th>
                    <th colspan="2" scope="colgroup">A.M.</th>
                    <th colspan="2" scope="colgroup">P.M.</th>
                    <th colspan="{{ $hasOvertime ? 5 : 4 }}"></th>
                </tr>
                <tr>
                    <th scope="col" class="dtr-grid-start">Day</th>
                    <th scope="col" class="dtr-grid-start">Arrival</th>
                    <th scope="col" class="dtr-grid-start">Departure</th>
                    <th scope="col" class="dtr-grid-start">Arrival</th>
                    <th scope="col" class="dtr-grid-start">Departure</th>
                    @if ($hasOvertime)
                        <th scope="col" class="dtr-grid-start">O.T.</th>
                    @endif
                    <th scope="col">Late</th>
                    {{-- The soft hyphen lets a narrow column break the word as "Under-time". --}}
                    <th scope="col">Under&shy;time</th>
                    <th scope="col" class="dtr-grid-start">Status</th>
                    <th scope="col" class="dtr-grid-start">Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr @class(['is-off' => $row['off'], 'is-absent' => $row['absent']])>
                        <th scope="row" class="dtr-grid-start dtr-record-date">{{ $row['label'] }}</th>
                        @foreach (DtrSlot::cases() as $slot)
                            @php($cell = $row['slots'][$slot->value])
                            <td class="dtr-record-time">
                                @php($trigger = $fill && ($cell['printed'] !== null || $cell['filled'] !== null || $cell['fillable']) ? $fill(['date' => $row['date'], 'slot' => $slot->value]) : null)
                                @if ($trigger?->isVisible())
                                    {{ $trigger }}
                                @elseif ($cell['filled'] !== null)
                                    <span class="dtr-grid-hr" @if ($cell['printed'] !== null) title="Printed {{ $cell['printed'] }}" @endif><span class="dtr-grid-time">{{ $cell['filled'] }}</span> <span class="dtr-grid-hr-tag">HR</span></span>
                                @elseif ($cell['printed'] === null && $cell['away'] !== null)
                                    <span class="dtr-grid-hr" title="{{ $cell['away']['label'] }}: the schedule's time"><span class="dtr-grid-time">{{ $cell['away']['time'] }}</span> <span class="dtr-grid-hr-tag">{{ $cell['away']['tag'] }}</span></span>
                                @else
                                    {{ $cell['printed'] }}
                                @endif
                            </td>
                        @endforeach
                        @if ($hasOvertime)
                            <td class="dtr-record-time">{{ $row['ot'] }}</td>
                        @endif
                        <td class="dtr-grid-value">{{ $row['late'] }}</td>
                        <td class="dtr-grid-value">{{ $row['undertime'] }}</td>
                        <td>
                            <span class="dtr-grid-badges">
                                @if ($row['status'])
                                    <x-filament::badge :color="$row['status']->getColor()">{{ $row['status']->getLabel() }}</x-filament::badge>
                                @endif
                                @php($exceptionTrigger = $addException && $row['untapped'] ? $addException(['date' => $row['date']]) : null)
                                @if ($exceptionTrigger?->isVisible())
                                    {{ $exceptionTrigger }}
                                @endif
                                @foreach ($editException ? $row['exceptions'] : [] as $exceptionId)
                                    @php($editTrigger = $editException(['exception' => $exceptionId]))
                                    @if ($editTrigger->isVisible())
                                        {{ $editTrigger }}
                                    @endif
                                @endforeach
                            </span>
                        </td>
                        {{-- The whole note on hover: on a laptop a long one ends in "…". --}}
                        <td class="dtr-record-notes" @if ($row['notes'] !== []) title="{{ collect($row['notes'])->pluck(0)->implode(' · ') }}" @endif>
                            @foreach ($row['notes'] as [$note, $needsALook])
                                <span @class(['is-warning' => $needsALook])>{{ $note }}</span>@unless ($loop->last) · @endunless
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @unless ($notComputed)
        <div class="dtr-grid-legend dtr-record-legend">
            @if ($fill)
                <span><span class="dtr-grid-add">+ Add</span> a blank to fill from the signed DTR</span>
            @endif
            @if ($addException)
                <span><span class="dtr-grid-ob">+ OB/Leave</span> an OB, travel order, leave or gate pass for a day with no tap</span>
            @endif
            @if ($editException)
                <span><span class="dtr-grid-ob dtr-grid-edit"><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare" /> Edit</span> change or remove an OB, travel order, leave or gate pass recorded by mistake</span>
            @endif
            <span><span class="dtr-grid-hr-tag">HR</span> a time HR copied in</span>
            <span><span class="dtr-grid-hr-tag">OB</span> <span class="dtr-grid-hr-tag">TO</span> <span class="dtr-grid-hr-tag">GP</span> the schedule's time on an OB, travel order or gate pass</span>
            <span><span class="dtr-record-swatch" aria-hidden="true"></span> not a work date</span>
        </div>
    @endunless
</div>
