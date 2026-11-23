@php
    $minus = "\u{2212}";
    $totals = [];

    foreach ($deductions as $deduction) {
        $code = $deduction['leave_type']->value;
        $totals[$code] = bcadd($totals[$code] ?? '0.000', $deduction['days'], 3);
    }
@endphp
{{-- This year's deductions, in the Name card's place (HR, 2026-11-23): one line each, the leave and days at the end in red. --}}
@if ($deductions === [])
    <p class="dtr-deductions-empty">No deductions in {{ $year }}.</p>
@else
    <ul role="list" class="dtr-deductions">
        @foreach ($deductions as $deduction)
            <li class="dtr-deduction">
                <span class="dtr-deduction-what">
                    <span class="dtr-deduction-title">{{ $deduction['title'] }}</span>
                    @if (filled($deduction['detail']))
                        <span class="dtr-deduction-detail">{{ $deduction['detail'] }}</span>
                    @endif
                </span>
                <span class="dtr-deduction-days">{{ $deduction['leave_type']->value }} {{ $minus }}{{ ltrim($deduction['days'], '-') }}</span>
            </li>
        @endforeach
    </ul>
    <p class="dtr-deductions-total">
        <span>Total</span>
        <span class="dtr-deduction-days">
            {{-- VL, SL, SPL, Wellness, as the cards above. --}}
            {{ collect(\App\Enums\LeaveType::tracked())->filter(fn ($type): bool => isset($totals[$type->value]))->map(fn ($type): string => "{$type->value} {$minus}".ltrim($totals[$type->value], '-'))->implode(' · ') }}
        </span>
    </p>
@endif
