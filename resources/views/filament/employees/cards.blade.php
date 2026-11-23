@php
    use App\Enums\LeaveType;
    use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;
    use Filament\Support\Icons\Heroicon;

    // Each balance: its name, its tint, a small icon and the large faint one in the corner.
    $leaveCards = [
        'VL' => ['Vacation', 'is-vacation', Heroicon::OutlinedPaperAirplane, Heroicon::Sun],
        'SL' => ['Sick', 'is-sick', Heroicon::OutlinedCheckCircle, Heroicon::Plus],
        'SPL' => ['Special privilege', 'is-special', Heroicon::OutlinedStar, Heroicon::Star],
        'WL' => ['Wellness', 'is-wellness', Heroicon::OutlinedHeart, Heroicon::Heart],
    ];
@endphp
{{--
    An employee's figures at a glance, after HR's reference (2026-11-23): last month's
    tardiness, then each balance, on soft tints with a large faint icon in the corner. The
    schedule left the row; it is on the Schedules tab. Those who earn no leave have none.
--}}
@if ($earns)
    <dl class="dtr-emp-cards">
        <div class="dtr-emp-card is-tardiness">
            <x-filament::icon :icon="Heroicon::OutlinedClock" class="dtr-emp-card-icon" />
            <x-filament::icon :icon="Heroicon::ChartPie" class="dtr-emp-card-art" />
            <dt class="dtr-emp-card-label">Tardiness · {{ $month->format('F') }}</dt>
            @if ($lastMonth !== null)
                <dd class="dtr-emp-card-value">{{ $lastMonth->lateCount }} {{ str('late')->plural($lastMonth->lateCount) }}</dd>
                <dd class="dtr-emp-card-detail">
                    {{ implode(' · ', array_filter([
                        DtrUploadInfolist::hours($lastMonth->late).' late',
                        $lastMonth->undertime > 0 ? DtrUploadInfolist::hours($lastMonth->undertime).' undertime' : null,
                        $lastMonth->absent > 0 ? $lastMonth->absent.' '.($lastMonth->absent == 1 ? 'day' : 'days').' absent' : null,
                    ])) }}
                </dd>
            @else
                <dd class="dtr-emp-card-value">No DTR</dd>
                <dd class="dtr-emp-card-detail">uploaded for the month</dd>
            @endif
        </div>

        @foreach (LeaveType::tracked() as $type)
            @php([$label, $tone, $icon, $art] = $leaveCards[$type->value])
            <div class="dtr-emp-card {{ $tone }}">
                <x-filament::icon :icon="$icon" class="dtr-emp-card-icon" />
                <x-filament::icon :icon="$art" class="dtr-emp-card-art" />
                <dt class="dtr-emp-card-label">{{ $label }}</dt>
                <dd class="dtr-emp-card-value">{{ $balances[$type->value] }}</dd>
                <dd class="dtr-emp-card-detail">days left</dd>
            </div>
        @endforeach
    </dl>
@endif
