{{--
    The staff dashboard, laid out after HR's reference (2026-11-20): a heading with the
    month picker, four figures, the week's chart beside the quick actions, then the month's
    progress, and today's leave at the foot. The page (App\Filament\Pages\Dashboard) hands
    over the figures DashboardFigures works out. The bars and the gauge are plain HTML and
    SVG, so no chart library is loaded, and every figure is also written out in words.
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
    $figures = [
        ['k1', Heroicon::OutlinedClock, 'is-info', $month['dayEquivalent'], 'days', "Late and undertime in {$lastMonth->format('F Y')}", null, null],
        ['k2', Heroicon::OutlinedUserMinus, 'is-success', (string) $month['absent'], 'days', "Absences in {$monthName}", null, null],
        ['k3', Heroicon::OutlinedDocumentCheck, 'is-warning', (string) $pending, null, 'Leave to approve', 'Review them', LeaveApplicationResource::getUrl('index', ['filters' => ['status' => ['value' => LeaveApplicationStatus::Pending->value]]])],
        ['k4', Heroicon::OutlinedMapPin, 'is-primary', (string) count($outToday), null, 'Out today', 'See who', '#dash-o'],
    ];
@endphp

@include('filament.dashboard-styles')

<div class="dtr-dash">
    {{-- The heading HR sees; the page's own h1 stays for screen readers, so this one is not read twice. --}}
    <div class="dtr-dash-top">
        <div class="dtr-dash-stack">
            <p class="dtr-dash-heading" aria-hidden="true">Dashboard</p>
            <p class="dtr-dash-sub">{{ $lastMonth->format('F Y') }}'s DTRs, and today's leave</p>
        </div>
        <label class="dtr-dash-month">
            <span class="fi-sr-only">Month shown</span>
            {{-- The months there is something to show for, as DTR Uploads' month filter lists them. --}}
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="month">
                    @foreach ($months as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
    </div>

    {{-- K: four figures. --}}
    @foreach ($figures as [$key, $icon, $tone, $value, $unit, $label, $link, $url])
        <section class="dtr-dash-card dtr-dash-kpi dtr-dash-{{ $key }}" aria-labelledby="dash-{{ $key }}">
            <span class="dtr-dash-kpi-icon {{ $tone }}" aria-hidden="true"><x-filament::icon :icon="$icon" /></span>
            <div class="dtr-dash-kpi-text">
                <p class="dtr-dash-num">{{ $value }} @if ($unit)<small>{{ $unit }}</small>@endif</p>
                <h2 id="dash-{{ $key }}" class="dtr-dash-sub">{{ $label }}</h2>
                @if ($link)
                    <a class="dtr-dash-link" href="{{ $url }}">{{ $link }}</a>
                @endif
            </div>
        </section>
    @endforeach

    {{-- A: the month's late and undertime, by weekday. --}}
    <section class="dtr-dash-card dtr-dash-a" aria-labelledby="dash-a">
        <div class="dtr-dash-head">
            <div class="dtr-dash-titled">
                <span class="dtr-dash-title-icon" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedChartBar" /></span>
                <div>
                    <h2 id="dash-a" class="dtr-dash-title">Attendance overview</h2>
                    <p class="dtr-dash-sub">Late and undertime in {{ $lastMonth->format('F Y') }}, by weekday</p>
                </div>
            </div>
            <ul class="dtr-dash-legend">
                <li><span class="dtr-dash-key is-late"></span>Late ({{ $month['late'] }} min)</li>
                <li><span class="dtr-dash-key is-undertime"></span>Undertime ({{ $month['undertime'] }} min)</li>
            </ul>
        </div>

        @if ($month['dtrs'] === 0)
            <p class="dtr-dash-muted">No DTRs for {{ $monthName }} yet. <a class="dtr-dash-link" href="{{ $uploadUrl }}">Upload them</a></p>
        @else
            <div class="dtr-dash-plot">
                {{-- The scale, in minutes: the tallest day, half of it, none. --}}
                <div class="dtr-dash-axis" aria-hidden="true">
                    <span>{{ number_format($tallest) }}</span>
                    <span>{{ number_format(intdiv($tallest, 2)) }}</span>
                    <span>0</span>
                </div>
                <div class="dtr-dash-chart">
                    @foreach ($month['byWeekday'] as $weekday => $minutes)
                        <div class="dtr-dash-col" tabindex="0" aria-label="{{ $weekdays[$weekday] }}: {{ $minutes['late'] }} minutes late, {{ $minutes['undertime'] }} minutes under">
                            <span class="dtr-dash-tip" aria-hidden="true">{{ $minutes['late'] }} min late · {{ $minutes['undertime'] }} min under</span>
                            <div class="dtr-dash-track">
                                <span class="is-late" style="height: {{ $minutes['late'] / $tallest * 100 }}%"></span>
                                <span class="is-undertime" style="height: {{ $minutes['undertime'] / $tallest * 100 }}%"></span>
                            </div>
                            <span class="dtr-dash-day" aria-hidden="true">{{ mb_substr($weekdays[$weekday], 0, 3) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="dtr-dash-muted">{{ $month['dayEquivalent'] }} days of leave by the CSC table, from {{ $month['dtrs'] }} of {{ $month['staff'] }} staff's DTRs. The figures refresh every 10 minutes.</p>
    </section>

    {{-- C: quick actions. --}}
    <section class="dtr-dash-card dtr-dash-c" aria-labelledby="dash-c">
        <div class="dtr-dash-titled">
            <span class="dtr-dash-title-icon" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedBolt" /></span>
            <h2 id="dash-c" class="dtr-dash-title">Quick actions</h2>
        </div>
        <div class="dtr-dash-actions is-tiles">
            @foreach ($actions as [$label, $icon, $url])
                <a class="dtr-dash-action" href="{{ $url }}">
                    <span class="dtr-dash-action-icon"><x-filament::icon :icon="$icon" /></span>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </section>

    {{-- D: the month's DTRs uploaded, and marked final. --}}
    <section class="dtr-dash-card dtr-dash-d" aria-labelledby="dash-d">
        <div class="dtr-dash-head">
            <div>
                <h2 id="dash-d" class="dtr-dash-title">DTRs for {{ $monthName }}</h2>
                <p class="dtr-dash-sub">Staff who earn leave credits</p>
            </div>
            <a class="dtr-dash-link" href="{{ DtrUploadResource::getUrl('index') }}">View all</a>
        </div>
        {{-- Each bar under its label and count; the second is closing the month, the uploaded HR has marked final. --}}
        <div class="dtr-dash-meters">
            <div class="dtr-dash-stack">
                <div class="dtr-dash-between">
                    <span>Uploaded</span>
                    <span class="dtr-dash-count">{{ $month['dtrs'] }} of {{ $month['staff'] }}</span>
                </div>
                <div class="dtr-dash-progress" role="progressbar" aria-label="DTRs uploaded" aria-valuemin="0" aria-valuemax="{{ $month['staff'] }}" aria-valuenow="{{ $month['dtrs'] }}">
                    <span style="width: {{ $month['staff'] > 0 ? $month['dtrs'] / $month['staff'] * 100 : 0 }}%"></span>
                </div>
            </div>
            <div class="dtr-dash-stack">
                <div class="dtr-dash-between">
                    <span>Final</span>
                    <span class="dtr-dash-count">{{ $finalCount }} of {{ $month['dtrs'] }}</span>
                </div>
                <div class="dtr-dash-progress" role="progressbar" aria-label="DTRs final" aria-valuemin="0" aria-valuemax="{{ $month['dtrs'] }}" aria-valuenow="{{ $finalCount }}">
                    <span style="width: {{ $month['dtrs'] > 0 ? min(100, $finalCount / $month['dtrs'] * 100) : 0 }}%"></span>
                </div>
            </div>
        </div>
    </section>

    {{-- E: what to do next. --}}
    <section class="dtr-dash-card dtr-dash-e" aria-labelledby="dash-e">
        @switch ($nextStep['step'])
            @case('opening-balances')
                <h2 id="dash-e" class="dtr-dash-title">Opening balances</h2>
                <div class="dtr-dash-note">
                    <span class="dtr-dash-bulb" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedLightBulb" /></span>
                    <p>{{ $nextStep['done'] }} of {{ $nextStep['of'] }} staff have theirs. The system starts counting leave on {{ $takeover->format('F j') }}, from these balances.</p>
                </div>
                <a class="dtr-dash-link" href="{{ LeaveOpeningBalances::getUrl() }}">Open Opening Balances</a>
                @break
            @case('uploads')
                <h2 id="dash-e" class="dtr-dash-title">Upload the rest</h2>
                <div class="dtr-dash-note">
                    <span class="dtr-dash-bulb" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedLightBulb" /></span>
                    <p>{{ $nextStep['done'] }} of {{ $nextStep['of'] }} staff have a DTR for {{ $monthName }}. Their late, undertime and absences count only once it is in.</p>
                </div>
                <a class="dtr-dash-link" href="{{ $uploadUrl }}">Upload DTRs</a>
                @break
            @default
                <h2 id="dash-e" class="dtr-dash-title">All caught up</h2>
                <div class="dtr-dash-note">
                    <span class="dtr-dash-bulb" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedLightBulb" /></span>
                    <p>Every DTR for {{ $monthName }} is in. The Monthly Summary has the month's totals.</p>
                </div>
                <a class="dtr-dash-link" href="{{ MonthlySummary::getUrl() }}">Open Monthly Summary</a>
        @endswitch
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

    {{-- H: the offices whose month is not closed, a DTR not uploaded or not final, furthest behind first. --}}
    <section class="dtr-dash-card dtr-dash-h" aria-labelledby="dash-h">
        <div>
            <h2 id="dash-h" class="dtr-dash-title">Offices not done</h2>
            <p class="dtr-dash-sub">{{ $lastMonth->format('F Y') }}, furthest behind first</p>
        </div>

        {{-- Three, so the card is no taller than the ones beside it; View all lists the rest. --}}
        @forelse (array_slice($officesNotDone, 0, 3) as $office)
            {{-- The figures under the name: office names run long, and side by side both wrapped. --}}
            <div class="dtr-dash-stack">
                <div>
                    <p class="dtr-dash-office">{{ $office['office'] }}</p>
                    <p class="dtr-dash-muted">uploaded {{ $office['uploaded'] }} of {{ $office['staff'] }} · final {{ $office['final'] }}</p>
                </div>
                <div class="dtr-dash-progress is-thin" aria-hidden="true">
                    <span style="width: {{ $office['final'] / $office['staff'] * 100 }}%"></span>
                </div>
            </div>
        @empty
            <p class="dtr-dash-muted">{{ $month['staff'] === 0 ? 'No staff who earn leave credits yet.' : "Every office's DTRs for {$monthName} are in and final." }}</p>
        @endforelse

        {{-- At the foot: how many more, and the DTRs not final; up in the head the link wrapped under the title. --}}
        <div class="dtr-dash-between dtr-dash-foot">
            <span class="dtr-dash-muted">
                @if (count($officesNotDone) > 3)
                    + {{ count($officesNotDone) - 3 }} more {{ str('office')->plural(count($officesNotDone) - 3) }}
                @endif
            </span>
            <a class="dtr-dash-link" href="{{ DtrUploadResource::getUrl('index', ['filters' => ['not_final' => ['isActive' => true]]]) }}">View all</a>
        </div>
    </section>

    {{-- O: who is out today. --}}
    <section id="dash-o" class="dtr-dash-card dtr-dash-o" aria-labelledby="dash-o-title">
        <div>
            <h2 id="dash-o-title" class="dtr-dash-title">Out today</h2>
            <p class="dtr-dash-sub">On leave, official business or travel</p>
        </div>
        @if ($outToday === [])
            <div class="dtr-dash-empty">
                <x-filament::icon :icon="Heroicon::OutlinedUserGroup" aria-hidden="true" />
                <p>Everyone is in today.</p>
            </div>
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

    {{-- I: the newest leave applications. --}}
    <section class="dtr-dash-card dtr-dash-i" aria-labelledby="dash-i">
        <div class="dtr-dash-head">
            <h2 id="dash-i" class="dtr-dash-title">Recent leave</h2>
            <a class="dtr-dash-link" href="{{ LeaveApplicationResource::getUrl('index') }}">See all</a>
        </div>

        @if ($recent->isEmpty())
            <div class="dtr-dash-empty">
                <x-filament::icon :icon="Heroicon::OutlinedDocumentText" aria-hidden="true" />
                <p>No leave filed yet.</p>
            </div>
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
