{{--
    The staff dashboard, laid out after HR's reference: a grid of cards, each answering
    one question about the office. The page (App\Filament\Pages\Dashboard) hands over the
    figures DashboardFigures works out. The bars and the gauge are plain HTML and SVG, so
    no chart library is loaded, and every figure is also written out in words.
--}}
@php
    use App\Enums\LeaveApplicationStatus;
    use App\Filament\Pages\DutyRoster;
    use App\Filament\Pages\LeaveOpeningBalances;
    use App\Filament\Pages\MonthlySummary;
    use App\Filament\Resources\DtrUploads\DtrUploadResource;
    use App\Filament\Resources\Employees\EmployeeResource;
    use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
    use App\Models\Employee;
    use Filament\Support\Icons\Heroicon;

    $initials = fn (Employee $employee): string => mb_strtoupper(mb_substr($employee->first_name, 0, 1).mb_substr($employee->last_name, 0, 1));
    // "1.000" as "1", "0.500" as "0.5".
    $days = fn (string $value): string => rtrim(rtrim($value, '0'), '.');
    $monthName = $lastMonth->format('F');
    $uploadUrl = DtrUploadResource::getUrl('index', ['action' => 'uploadDtrs']);
    $weekdays = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $tallest = max(1, ...array_map(fn (array $minutes): int => $minutes['late'] + $minutes['undertime'], $month['byWeekday']));
    $leaveColors = ['Vacation' => 'var(--success-500)', 'Sick' => 'var(--info-500)', 'Special privilege' => 'var(--warning-400)', 'Wellness' => 'var(--danger-400)', 'Others' => 'var(--gray-400)'];
    $actions = [
        ['Upload DTRs', Heroicon::OutlinedArrowUpTray, $uploadUrl],
        ['File leave', Heroicon::OutlinedDocumentPlus, LeaveApplicationResource::getUrl('index', ['action' => 'fileLeave'])],
        ['Add employee', Heroicon::OutlinedUserPlus, EmployeeResource::getUrl('index', ['action' => 'create'])],
        ['Duty roster', Heroicon::OutlinedCalendarDays, DutyRoster::getUrl()],
        ['Summary', Heroicon::OutlinedDocumentChartBar, MonthlySummary::getUrl()],
    ];
@endphp

{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    .dtr-dash {
        --dash-card: var(--color-white);
        --dash-line: color-mix(in oklab, var(--gray-950) 6%, transparent);
        --dash-track: var(--gray-100);
        --dash-text: var(--gray-950);
        --dash-muted: var(--gray-500);

        display: grid;
        gap: calc(var(--spacing) * 4);
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas: "a" "b" "c" "d" "e" "f" "g" "h" "i";
    }

    .dark .dtr-dash {
        --dash-card: var(--gray-900);
        --dash-line: color-mix(in oklab, var(--color-white) 10%, transparent);
        --dash-track: color-mix(in oklab, var(--color-white) 6%, transparent);
        --dash-text: var(--color-white);
        --dash-muted: var(--gray-400);
    }

    @media (min-width: 48rem) {
        .dtr-dash {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-template-areas: "a a" "b c" "d e" "f g" "h i";
        }
    }

    /* The reference's layout: the chart wide on top, the quick actions down the right, four cards along the foot. */
    @media (min-width: 80rem) {
        .dtr-dash {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            grid-template-areas: "a a b c" "d e e c" "f g h i";
        }
    }

    .dtr-dash-a { grid-area: a; }
    .dtr-dash-b { grid-area: b; }
    .dtr-dash-c { grid-area: c; }
    .dtr-dash-d { grid-area: d; }
    .dtr-dash-e { grid-area: e; }
    .dtr-dash-f { grid-area: f; }
    .dtr-dash-g { grid-area: g; }
    .dtr-dash-h { grid-area: h; }
    .dtr-dash-i { grid-area: i; }

    .dtr-dash-card {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 3);
        min-width: 0;
        padding: calc(var(--spacing) * 5);
        border-radius: 1rem;
        background-color: var(--dash-card);
        box-shadow: 0 0 0 1px var(--dash-line);
        color: var(--dash-text);
    }

    .dtr-dash-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-dash-title {
        font-size: var(--text-base);
        line-height: var(--text-base--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .dtr-dash-sub,
    .dtr-dash-muted {
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        color: var(--dash-muted);
    }

    .dtr-dash-big,
    .dtr-dash-num {
        font-weight: var(--font-weight-semibold);
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .dtr-dash-big {
        font-size: 2.25rem;
        line-height: 1.1;
    }

    .dtr-dash-num {
        font-size: 1.75rem;
        line-height: 1.15;
    }

    .dtr-dash-big small,
    .dtr-dash-num small {
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        letter-spacing: normal;
        color: var(--dash-muted);
    }

    .dtr-dash-link {
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        color: var(--primary-700);
    }

    .dark .dtr-dash-link {
        color: var(--primary-400);
    }

    .dtr-dash-link:hover,
    .dtr-dash-link:focus-visible {
        text-decoration: underline;
    }

    .dtr-dash-key {
        display: inline-block;
        flex: none;
        width: 0.625rem;
        height: 0.625rem;
        border-radius: 0.1875rem;
        background-color: var(--key);
    }

    .dtr-dash-legend {
        display: flex;
        flex-wrap: wrap;
        gap: calc(var(--spacing) * 3);
        font-size: var(--text-xs);
        color: var(--dash-muted);
    }

    .dtr-dash-legend li {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 1.5);
    }

    /* A: a column per weekday, the day's late and undertime minutes stacked on a grey track. */
    .dtr-dash-chart {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: calc(var(--spacing) * 3);
        height: 12rem;
        margin-top: calc(var(--spacing) * 6);
    }

    .dtr-dash-col {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 2);
        border-radius: 0.5rem;
    }

    .dtr-dash-col:focus-visible {
        outline: 2px solid var(--gray-500);
        outline-offset: 2px;
    }

    .dtr-dash-track {
        display: flex;
        flex: 1;
        flex-direction: column;
        justify-content: flex-end;
        overflow: hidden;
        border-radius: 0.5rem;
        background-color: var(--dash-track);
    }

    .dtr-dash-track > span {
        display: block;
        flex: none;
    }

    .dtr-dash-day {
        font-size: var(--text-xs);
        text-align: center;
        color: var(--dash-muted);
    }

    /* The day's figures over its column, on hover or keyboard focus. */
    .dtr-dash-tip {
        position: absolute;
        bottom: calc(100% + 0.375rem);
        left: 50%;
        z-index: 1;
        padding: calc(var(--spacing) * 1.5) calc(var(--spacing) * 2);
        border-radius: 0.5rem;
        background-color: var(--gray-950);
        color: var(--color-white);
        font-size: var(--text-xs);
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transform: translateX(-50%);
        transition: opacity 120ms;
    }

    .dtr-dash-col:hover .dtr-dash-tip,
    .dtr-dash-col:focus-visible .dtr-dash-tip {
        opacity: 1;
    }

    /* B: three figures, one under the other. */
    .dtr-dash-stats {
        display: grid;
    }

    .dtr-dash-stat {
        display: grid;
        gap: calc(var(--spacing) * 1);
        padding-block: calc(var(--spacing) * 3);
    }

    .dtr-dash-stat + .dtr-dash-stat {
        border-top: 1px solid var(--dash-line);
    }

    .dtr-dash-stat:first-child {
        padding-top: 0;
    }

    .dtr-dash-stat:last-child {
        padding-bottom: 0;
    }

    /* C: the quick actions as tiles, then who is out today. */
    .dtr-dash-actions {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: calc(var(--spacing) * 2);
    }

    .dtr-dash-action {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: calc(var(--spacing) * 1.5);
        font-size: var(--text-xs);
        line-height: 1.25;
        text-align: center;
        color: var(--gray-700);
    }

    .dark .dtr-dash-action {
        color: var(--gray-300);
    }

    .dtr-dash-action-icon {
        display: grid;
        place-items: center;
        width: calc(var(--spacing) * 11);
        height: calc(var(--spacing) * 11);
        border-radius: 0.75rem;
        box-shadow: 0 0 0 1px var(--dash-line);
    }

    .dtr-dash-action:hover .dtr-dash-action-icon,
    .dtr-dash-action:focus-visible .dtr-dash-action-icon {
        background-color: var(--dash-track);
    }

    .dtr-dash-action:focus-visible {
        outline: none;
    }

    .dtr-dash-action:focus-visible .dtr-dash-action-icon {
        outline: 2px solid var(--gray-500);
        outline-offset: 2px;
    }

    .dtr-dash-action-icon > svg {
        width: calc(var(--spacing) * 5);
        height: calc(var(--spacing) * 5);
    }

    .dtr-dash-divider {
        border-top: 1px solid var(--dash-line);
    }

    .dtr-dash-people {
        display: flex;
        flex-wrap: wrap;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-dash-person {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: calc(var(--spacing) * 1);
        width: calc(var(--spacing) * 14);
        font-size: var(--text-xs);
        text-align: center;
    }

    .dtr-dash-person > span:last-child {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dtr-dash-avatar {
        display: grid;
        flex: none;
        place-items: center;
        width: calc(var(--spacing) * 10);
        height: calc(var(--spacing) * 10);
        border-radius: 9999px;
        background-color: var(--primary-100);
        color: var(--primary-800);
        font-size: var(--text-xs);
        font-weight: var(--font-weight-semibold);
    }

    .dark .dtr-dash-avatar {
        background-color: color-mix(in oklab, var(--primary-400) 20%, transparent);
        color: var(--primary-300);
    }

    /* D and H: progress bars. */
    .dtr-dash-progress {
        height: 0.75rem;
        overflow: hidden;
        border-radius: 9999px;
        background-color: var(--dash-track);
    }

    .dtr-dash-progress.is-thin {
        height: 0.5rem;
    }

    .dtr-dash-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background-color: var(--success-500);
    }

    .dtr-dash-between {
        display: flex;
        justify-content: space-between;
        gap: calc(var(--spacing) * 2);
        font-size: var(--text-sm);
    }

    /* E: the next step, with a bulb beside it. */
    .dtr-dash-e {
        flex-direction: row;
        align-items: flex-start;
    }

    .dtr-dash-bulb {
        display: grid;
        flex: none;
        place-items: center;
        width: calc(var(--spacing) * 11);
        height: calc(var(--spacing) * 11);
        border-radius: 9999px;
        background-color: color-mix(in oklab, var(--warning-400) 20%, transparent);
        color: var(--warning-700);
    }

    .dark .dtr-dash-bulb {
        color: var(--warning-300);
    }

    .dtr-dash-bulb > svg {
        width: calc(var(--spacing) * 6);
        height: calc(var(--spacing) * 6);
    }

    /* F: one bar cut into the types, then the types with their shares. */
    .dtr-dash-segments {
        display: flex;
        gap: 0.1875rem;
        height: 0.75rem;
    }

    .dtr-dash-segments > span {
        border-radius: 0.25rem;
        background-color: var(--key);
    }

    .dtr-dash-breakdown {
        display: grid;
        gap: calc(var(--spacing) * 1.5);
        font-size: var(--text-sm);
    }

    .dtr-dash-breakdown li {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 2);
    }

    .dtr-dash-breakdown li > span:last-child {
        margin-inline-start: auto;
        color: var(--dash-muted);
        font-variant-numeric: tabular-nums;
    }

    /* G: a half-circle gauge, the share written under it. */
    .dtr-dash-gauge {
        position: relative;
        width: 100%;
        max-width: 15rem;
        margin-inline: auto;
    }

    .dtr-dash-gauge svg {
        display: block;
        width: 100%;
    }

    .dtr-dash-gauge path {
        fill: none;
        stroke-width: 16;
        stroke-linecap: round;
    }

    .dtr-dash-gauge .is-track {
        stroke: var(--dash-track);
    }

    .dtr-dash-gauge .is-value {
        stroke: var(--success-500);
    }

    .dtr-dash-gauge p {
        position: absolute;
        inset-inline: 0;
        bottom: 0;
        text-align: center;
    }

    /* I: the newest leave applications. */
    .dtr-dash-rows {
        display: grid;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-dash-row {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 3);
        min-width: 0;
    }

    .dtr-dash-row-text {
        display: grid;
        flex: 1;
        min-width: 0;
        font-size: var(--text-sm);
    }

    .dtr-dash-row-text > span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: var(--font-weight-medium);
    }

    .dtr-dash-row-end {
        display: grid;
        justify-items: end;
        gap: calc(var(--spacing) * 1);
        font-size: var(--text-sm);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
</style>

<div class="dtr-dash">
    {{-- A: last month's late and undertime, by weekday. --}}
    <section class="dtr-dash-card dtr-dash-a" aria-labelledby="dash-a">
        <div class="dtr-dash-head">
            <div>
                <p class="dtr-dash-big">{{ $month['dayEquivalent'] }} <small>days</small></p>
                <h2 id="dash-a" class="dtr-dash-sub">Late and undertime in {{ $lastMonth->format('F Y') }}, as days of leave by the CSC table</h2>
            </div>
            <ul class="dtr-dash-legend">
                <li><span class="dtr-dash-key" style="--key: var(--warning-400)"></span>Late ({{ $month['late'] }} min)</li>
                <li><span class="dtr-dash-key" style="--key: var(--info-400)"></span>Undertime ({{ $month['undertime'] }} min)</li>
            </ul>
        </div>

        @if ($month['dtrs'] === 0)
            <p class="dtr-dash-muted">No DTRs for {{ $monthName }} yet. <a class="dtr-dash-link" href="{{ $uploadUrl }}">Upload them</a></p>
        @else
            <div class="dtr-dash-chart">
                @foreach ($month['byWeekday'] as $weekday => $minutes)
                    <div class="dtr-dash-col" tabindex="0" aria-label="{{ $weekdays[$weekday] }}: {{ $minutes['late'] }} minutes late, {{ $minutes['undertime'] }} minutes under">
                        <span class="dtr-dash-tip" aria-hidden="true">{{ $minutes['late'] }} min late · {{ $minutes['undertime'] }} min under</span>
                        <div class="dtr-dash-track">
                            <span style="height: {{ $minutes['late'] / $tallest * 100 }}%; background-color: var(--warning-400)"></span>
                            <span style="height: {{ $minutes['undertime'] / $tallest * 100 }}%; background-color: var(--info-400)"></span>
                        </div>
                        <span class="dtr-dash-day" aria-hidden="true">{{ mb_substr($weekdays[$weekday], 0, 3) }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="dtr-dash-muted">From {{ $month['dtrs'] }} of {{ $month['staff'] }} staff's DTRs. The figures refresh every 10 minutes.</p>
    </section>

    {{-- B: three figures. --}}
    <section class="dtr-dash-card dtr-dash-b" aria-label="At a glance">
        <div class="dtr-dash-stats">
            <div class="dtr-dash-stat">
                <h2 class="dtr-dash-sub">Leave to approve</h2>
                <p class="dtr-dash-num">{{ $pending }}</p>
                <a class="dtr-dash-link" href="{{ LeaveApplicationResource::getUrl('index', ['filters' => ['status' => ['value' => LeaveApplicationStatus::Pending->value]]]) }}">Review them</a>
            </div>
            <div class="dtr-dash-stat">
                <h2 class="dtr-dash-sub">Out today</h2>
                <p class="dtr-dash-num">{{ count($outToday) }}</p>
                <p class="dtr-dash-muted">On leave, official business or travel</p>
            </div>
            <div class="dtr-dash-stat">
                <h2 class="dtr-dash-sub">Absences in {{ $monthName }}</h2>
                <p class="dtr-dash-num">{{ $month['absent'] }} <small>days</small></p>
                <p class="dtr-dash-muted">Work days with no tap and no leave</p>
            </div>
        </div>
    </section>

    {{-- C: quick actions, and who is out today. --}}
    <section class="dtr-dash-card dtr-dash-c" aria-labelledby="dash-c">
        <h2 id="dash-c" class="dtr-dash-title">Quick actions</h2>
        <div class="dtr-dash-actions">
            @foreach ($actions as [$label, $icon, $url])
                <a class="dtr-dash-action" href="{{ $url }}">
                    <span class="dtr-dash-action-icon"><x-filament::icon :icon="$icon" /></span>
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="dtr-dash-divider"></div>

        <h2 class="dtr-dash-title">Out today</h2>
        @if ($outToday === [])
            <p class="dtr-dash-muted">Everyone is in today.</p>
        @else
            <ul class="dtr-dash-people">
                @foreach ($outToday as ['employee' => $employee, 'reason' => $reason])
                    <li class="dtr-dash-person" title="{{ $employee->full_name }}: {{ $reason }}">
                        <span class="dtr-dash-avatar" aria-hidden="true">{{ $initials($employee) }}</span>
                        <span>{{ $employee->first_name }}<span class="fi-sr-only">{{ $employee->last_name }}, {{ $reason }}</span></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- D: last month's DTRs uploaded. --}}
    <section class="dtr-dash-card dtr-dash-d" aria-labelledby="dash-d">
        <div>
            <h2 id="dash-d" class="dtr-dash-title">DTRs for {{ $monthName }}</h2>
            <p class="dtr-dash-sub">Staff who earn leave credits</p>
        </div>
        <div class="dtr-dash-progress" role="progressbar" aria-label="DTRs uploaded" aria-valuemin="0" aria-valuemax="{{ $month['staff'] }}" aria-valuenow="{{ $month['dtrs'] }}">
            <span style="width: {{ $month['staff'] > 0 ? $month['dtrs'] / $month['staff'] * 100 : 0 }}%"></span>
        </div>
        <div class="dtr-dash-between">
            <span>{{ $month['dtrs'] }} uploaded</span>
            <span class="dtr-dash-muted">of {{ $month['staff'] }}</span>
        </div>
    </section>

    {{-- E: what to do next. --}}
    <section class="dtr-dash-card dtr-dash-e" aria-labelledby="dash-e">
        <span class="dtr-dash-bulb" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedLightBulb" /></span>
        <div style="display: grid; gap: 0.5rem;">
            @switch ($nextStep['step'])
                @case('opening-balances')
                    <h2 id="dash-e" class="dtr-dash-title">Enter the opening balances</h2>
                    <p class="dtr-dash-sub">{{ $nextStep['done'] }} of {{ $nextStep['of'] }} staff have theirs. The system starts counting leave on {{ $takeover->format('F j') }}, from these balances.</p>
                    <a class="dtr-dash-link" href="{{ LeaveOpeningBalances::getUrl() }}">Open Opening Balances</a>
                    @break
                @case('uploads')
                    <h2 id="dash-e" class="dtr-dash-title">Upload the rest of {{ $monthName }}'s DTRs</h2>
                    <p class="dtr-dash-sub">{{ $nextStep['done'] }} of {{ $nextStep['of'] }} staff have a DTR for {{ $monthName }}. Their late, undertime and absences count only once it is in.</p>
                    <a class="dtr-dash-link" href="{{ $uploadUrl }}">Upload DTRs</a>
                    @break
                @default
                    <h2 id="dash-e" class="dtr-dash-title">All caught up</h2>
                    <p class="dtr-dash-sub">Every DTR for {{ $monthName }} is in. The Monthly Summary has the month's totals.</p>
                    <a class="dtr-dash-link" href="{{ MonthlySummary::getUrl() }}">Open Monthly Summary</a>
            @endswitch
        </div>
    </section>

    {{-- F: this year's leave by type. --}}
    <section class="dtr-dash-card dtr-dash-f" aria-labelledby="dash-f">
        <div>
            <h2 id="dash-f" class="dtr-dash-title">Leave in {{ now()->year }}</h2>
            <p class="dtr-dash-sub">Days filed, by type</p>
        </div>
        <p class="dtr-dash-num">{{ $days($leave['total']) }} <small>days</small></p>

        @if ($leave['types'] === [])
            <p class="dtr-dash-muted">No leave filed this year yet.</p>
        @else
            <div class="dtr-dash-segments" aria-hidden="true">
                @foreach ($leave['types'] as $type)
                    <span style="width: {{ $type['percent'] }}%; --key: {{ $leaveColors[$type['label']] }}"></span>
                @endforeach
            </div>
            <ul class="dtr-dash-breakdown">
                @foreach ($leave['types'] as $type)
                    <li>
                        <span class="dtr-dash-key" style="--key: {{ $leaveColors[$type['label']] }}"></span>
                        <span>{{ $type['label'] }}</span>
                        <span>{{ $days($type['days']) }} · {{ $type['percent'] }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- G: the share of days at work begun on time. --}}
    <section class="dtr-dash-card dtr-dash-g" aria-labelledby="dash-g">
        <div>
            <h2 id="dash-g" class="dtr-dash-title">On time</h2>
            <p class="dtr-dash-sub">Days at work in {{ $monthName }} with no late arrival</p>
        </div>

        @if ($month['onTimeRate'] === null)
            <p class="dtr-dash-muted">No DTRs for {{ $monthName }} yet.</p>
        @else
            <div class="dtr-dash-gauge">
                <svg viewBox="0 0 200 110" aria-hidden="true">
                    <path class="is-track" d="M 20 100 A 80 80 0 0 1 180 100" pathLength="100" />
                    <path class="is-value" d="M 20 100 A 80 80 0 0 1 180 100" pathLength="100" stroke-dasharray="{{ $month['onTimeRate'] }} 100" />
                </svg>
                <p class="dtr-dash-num">{{ $month['onTimeRate'] }}%</p>
            </div>
        @endif
    </section>

    {{-- H: the offices furthest behind on last month's DTRs. --}}
    <section class="dtr-dash-card dtr-dash-h" aria-labelledby="dash-h">
        <div>
            <h2 id="dash-h" class="dtr-dash-title">Offices still missing DTRs</h2>
            <p class="dtr-dash-sub">{{ $lastMonth->format('F Y') }}, furthest behind first</p>
        </div>

        @forelse ($month['officesBehind'] as $office)
            <div style="display: grid; gap: 0.375rem;">
                <div class="dtr-dash-between">
                    <span>{{ $office['office'] }}</span>
                    <span class="dtr-dash-muted">{{ $office['uploaded'] }} of {{ $office['staff'] }}</span>
                </div>
                <div class="dtr-dash-progress is-thin" aria-hidden="true">
                    <span style="width: {{ $office['uploaded'] / $office['staff'] * 100 }}%"></span>
                </div>
            </div>
        @empty
            <p class="dtr-dash-muted">{{ $month['staff'] === 0 ? 'No staff who earn leave credits yet.' : "Every office's DTRs for {$monthName} are in." }}</p>
        @endforelse
    </section>

    {{-- I: the newest leave applications. --}}
    <section class="dtr-dash-card dtr-dash-i" aria-labelledby="dash-i">
        <div class="dtr-dash-head">
            <h2 id="dash-i" class="dtr-dash-title">Recent leave</h2>
            <a class="dtr-dash-link" href="{{ LeaveApplicationResource::getUrl('index') }}">See all</a>
        </div>

        @if ($recent->isEmpty())
            <p class="dtr-dash-muted">No leave filed yet.</p>
        @else
            <ul class="dtr-dash-rows">
                @foreach ($recent as $application)
                    @php
                        [$from, $to] = [$application->date_from, $application->date_to];
                        $when = match (true) {
                            $from->equalTo($to) => $from->format('M j'),
                            $from->isSameMonth($to) => $from->format('M j').'–'.$to->format('j'),
                            default => $from->format('M j').' – '.$to->format('M j'),
                        };
                    @endphp
                    <li class="dtr-dash-row">
                        <span class="dtr-dash-avatar" aria-hidden="true">{{ $initials($application->employee) }}</span>
                        <span class="dtr-dash-row-text">
                            <span>{{ $application->employee->full_name }}</span>
                            <span class="dtr-dash-muted">{{ $application->leave_type->getLabel() }} · {{ $when }}</span>
                        </span>
                        <span class="dtr-dash-row-end">
                            <span>{{ $days($application->days) }} {{ str('day')->plural((float) $application->days) }}</span>
                            <x-filament::badge size="sm" :color="$application->status->getColor()">{{ $application->status->getLabel() }}</x-filament::badge>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
