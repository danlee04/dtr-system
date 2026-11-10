{{--
    An employee's own dashboard, in the staff dashboard's cards: what leave they have
    left, what they came to do, their latest DTR to check against their copy, and the
    leave still waiting for HR. App\Filament\Pages\Dashboard hands over the figures.
--}}
@php
    use App\Enums\AttendanceStatus;
    use App\Filament\Employee\Pages\History;
    use App\Filament\Employee\Pages\LeaveTypes;
    use App\Filament\Employee\Pages\MyDtr;
    use App\Filament\Employee\Resources\MyLeaveApplications\MyLeaveApplicationResource;
    use App\Services\Attendance\ComputedDay;
    use Filament\Support\Icons\Heroicon;

    // "5.000" as "5", "0.500" as "0.5".
    $days = fn (string $value): string => rtrim(rtrim($value, '0'), '.');
    $count = fn (int|float $number, string $unit): string => $number.' '.str($unit)->plural((float) $number === 1.0 ? 1 : 2);
    // Each tracked balance, VL first, by its name and colour on the employee page and the leave chart.
    $balanceCards = [
        'VL' => ['Vacation', 'is-vacation', Heroicon::OutlinedSun, 'days left'],
        'SL' => ['Sick', 'is-sick', Heroicon::OutlinedHeart, 'days left'],
        'SPL' => ['Special privilege', 'is-special', Heroicon::OutlinedStar, 'days left, until Dec 31'],
        'WL' => ['Wellness', 'is-wellness', Heroicon::OutlinedSparkles, 'days left, until Dec 31'],
    ];
    $actions = [
        ['File leave', Heroicon::OutlinedDocumentPlus, MyLeaveApplicationResource::getUrl('index', ['action' => 'fileLeave']), true],
        ['My DTR', Heroicon::OutlinedClipboardDocumentCheck, MyDtr::getUrl(), false],
        ['History', Heroicon::OutlinedClock, History::getUrl(), false],
        ['Leave types', Heroicon::OutlinedQuestionMarkCircle, LeaveTypes::getUrl(), false],
    ];
@endphp

@include('filament.dashboard-styles')

@if ($employee === null)
    <x-filament::section>
        <x-filament::empty-state
            :contained="false"
            :icon="Heroicon::OutlinedUser"
            heading="Your account is not linked to a current employee"
            description="Ask HR to link it to your employee record."
        />
    </x-filament::section>
@else
    <div class="dtr-dash is-mine">
        {{-- A: the leave they have left. --}}
        <section class="dtr-dash-card dtr-dash-a" aria-labelledby="dash-a">
            <div>
                <h2 id="dash-a" class="dtr-dash-title">Leave balance</h2>
                <p class="dtr-dash-sub">Days left as of {{ today()->format('M j, Y') }}</p>
            </div>
            <dl class="dtr-profile-cards">
                {{-- Destructured here, not in an inline PHP directive: Blade would take that for the start of the PHP block in card C. --}}
                @foreach ($balanceCards as $code => [$label, $tone, $icon, $detail])
                    <div class="dtr-profile-card {{ $tone }}">
                        <span class="dtr-profile-card-icon" aria-hidden="true"><x-filament::icon :icon="$icon" /></span>
                        <dt class="dtr-profile-card-label">{{ $label }}</dt>
                        <dd class="dtr-profile-card-value">{{ $balances[$code] }}</dd>
                        <dd class="dtr-profile-card-detail">{{ $detail }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- B: what they came to do; filing leave is the main one. --}}
        <section class="dtr-dash-card dtr-dash-b" aria-labelledby="dash-b">
            <h2 id="dash-b" class="dtr-dash-title">Quick actions</h2>
            <div class="dtr-dash-actions">
                @foreach ($actions as [$label, $icon, $url, $main])
                    <a @class(['dtr-dash-action', 'is-main' => $main]) href="{{ $url }}">
                        <span class="dtr-dash-action-icon"><x-filament::icon :icon="$icon" /></span>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </section>

        {{-- C: their latest DTR, to check against their copy. --}}
        <section class="dtr-dash-card dtr-dash-c" aria-labelledby="dash-c">
            @if ($dtr === null)
                <h2 id="dash-c" class="dtr-dash-title">Your DTR</h2>
                <p class="dtr-dash-muted">No DTR uploaded for you yet. HR uploads each month's once the IT Unit prints it.</p>
            @else
                <div class="dtr-dash-head">
                    <div>
                        <h2 id="dash-c" class="dtr-dash-title">Your DTR for {{ $dtr->period_start->format('F') }}</h2>
                        <p class="dtr-dash-sub">As the app worked it out from the IT Unit's printout. Check it against your copy.</p>
                    </div>
                    <a class="dtr-dash-link" href="{{ MyDtr::getUrl() }}">Check my DTR</a>
                </div>

                @if ($computed === null)
                    <p class="dtr-dash-muted">Job order and contract of service DTRs are shown as printed, not computed.</p>
                @else
                    @php
                        $missing = array_sum(array_map(fn (ComputedDay $day): int => count($day->missing), $computed->days));
                        $absentOn = array_keys(array_filter($computed->days, fn (ComputedDay $day): bool => $day->status === AttendanceStatus::Absent));
                        $figures = [
                            ['Late', "{$computed->late} min", $computed->lateCount === 0 ? 'None' : $count($computed->lateCount, 'time'), null],
                            ['Undertime', "{$computed->undertime} min", $computed->undertimeCount === 0 ? 'None' : $count($computed->undertimeCount, 'time'), null],
                            ['Absent', $count($computed->absent, 'day'), match (true) {
                                $absentOn === [] => 'None',
                                count($absentOn) <= 3 => implode(', ', array_map(fn (string $date): string => date('M j', strtotime($date)), $absentOn)),
                                default => 'work days with no tap',
                            }, null],
                            ['Missing taps', (string) $missing, $missing > 0 ? 'on '.$count($computed->daysMissingTaps, 'date') : 'None', $missing > 0 ? 'is-warning' : null],
                        ];
                    @endphp
                    <dl class="dtr-profile-cards">
                        @foreach ($figures as [$label, $value, $detail, $tone])
                            <div @class(['dtr-profile-card', $tone])>
                                <dt class="dtr-profile-card-label">{{ $label }}</dt>
                                <dd class="dtr-profile-card-value">{{ $value }}</dd>
                                <dd class="dtr-profile-card-detail">{{ $detail }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="dtr-dash-muted">
                        Late and undertime: {{ $computed->late + $computed->undertime }} min, or {{ $computed->dayEquivalent }} day by the CSC table.
                        Something wrong? Bring your signed DTR to HR.
                    </p>
                @endif
            @endif
        </section>

        {{-- D: the leave still waiting for HR. --}}
        <section class="dtr-dash-card dtr-dash-d" aria-labelledby="dash-d">
            <div>
                <h2 id="dash-d" class="dtr-dash-title">Waiting for approval</h2>
                <p class="dtr-dash-sub">Filed, not yet approved by HR</p>
            </div>

            @if ($pending->isEmpty())
                <p class="dtr-dash-muted">Nothing waiting for approval.</p>
            @else
                <ul class="dtr-dash-rows">
                    @foreach ($pending as $application)
                        <li class="dtr-dash-row">
                            <span class="dtr-dash-avatar" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedDocumentText" /></span>
                            <span class="dtr-dash-row-text">
                                <span>{{ $application->leave_type->getLabel() }}</span>
                                <span class="dtr-dash-muted">{{ $application->dates() }} · {{ $days($application->days) }} {{ str('day')->plural((float) $application->days) }}</span>
                            </span>
                            <span class="dtr-dash-row-end">
                                <a class="dtr-dash-link" href="{{ route('leave-applications.print', $application) }}" target="_blank">
                                    Print<span class="fi-sr-only"> {{ $application->leave_type->getLabel() }}, {{ $application->dates() }}</span>
                                </a>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endif
