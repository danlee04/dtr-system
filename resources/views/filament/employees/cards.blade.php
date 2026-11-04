@php
    use App\Enums\LeaveType;
    use Filament\Support\Icons\Heroicon;

    // Each balance by its name and colour on the dashboard's leave chart.
    $leaveCards = [
        'VL' => ['Vacation', 'is-vacation', Heroicon::OutlinedSun],
        'SL' => ['Sick', 'is-sick', Heroicon::OutlinedHeart],
        'SPL' => ['Special privilege', 'is-special', Heroicon::OutlinedStar],
        'WL' => ['Wellness', 'is-wellness', Heroicon::OutlinedSparkles],
    ];
@endphp
{{-- An employee's figures at a glance, after HR's reference: last month first, then each balance, then their schedule. --}}
<dl class="dtr-profile-cards">
    @if ($earns)
        <div class="dtr-profile-card is-main">
            <span class="dtr-profile-card-icon" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedClock" /></span>
            <dt class="dtr-profile-card-label">{{ $month->format('F') }}</dt>
            @if ($lastMonth !== null)
                <dd class="dtr-profile-card-value">{{ $lastMonth->lateCount }} {{ str('late')->plural($lastMonth->lateCount) }}</dd>
                <dd class="dtr-profile-card-detail">
                    {{ implode(' · ', array_filter([
                        "{$lastMonth->late} min late",
                        $lastMonth->undertime > 0 ? "{$lastMonth->undertime} min undertime" : null,
                        $lastMonth->absent > 0 ? $lastMonth->absent.' '.($lastMonth->absent == 1 ? 'day' : 'days').' absent' : null,
                    ])) }}
                </dd>
            @else
                <dd class="dtr-profile-card-value">No DTR</dd>
                <dd class="dtr-profile-card-detail">uploaded for the month</dd>
            @endif
        </div>

        @foreach (LeaveType::tracked() as $type)
            @php([$label, $tone, $icon] = $leaveCards[$type->value])
            <div class="dtr-profile-card {{ $tone }}">
                <span class="dtr-profile-card-icon" aria-hidden="true"><x-filament::icon :icon="$icon" /></span>
                <dt class="dtr-profile-card-label">{{ $label }}</dt>
                <dd class="dtr-profile-card-value">{{ $balances[$type->value] }}</dd>
                <dd class="dtr-profile-card-detail">days left</dd>
            </div>
        @endforeach
    @endif

    <div class="dtr-profile-card">
        <span class="dtr-profile-card-icon" aria-hidden="true"><x-filament::icon :icon="Heroicon::OutlinedCalendarDays" /></span>
        <dt class="dtr-profile-card-label">Schedule</dt>
        <dd class="dtr-profile-card-value">{{ $schedule?->code ?? 'None' }}</dd>
        <dd class="dtr-profile-card-detail">{{ $schedule?->hours ?? 'No fixed schedule' }}</dd>
    </div>
</dl>
