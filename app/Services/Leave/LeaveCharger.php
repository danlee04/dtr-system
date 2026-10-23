<?php

namespace App\Services\Leave;

use App\Enums\LeaveType;

/**
 * Splits an application's days across the balances it may draw, in order,
 * and calls the rest leave without pay. VL is never drawn from SL. The days
 * with pay cover the earliest dates, so the days without pay are the last
 * ones, and a date can be split between the two.
 */
final class LeaveCharger
{
    /** @var array<string, list<string>> leave type code → the balances it draws, in order */
    private const array SOURCES = [
        'VL' => ['VL'],
        'FL' => ['VL'],
        'SL' => ['SL', 'VL'],
        'SPL' => ['SPL'],
        'WL' => ['WL'],
    ];

    /**
     * @param  array<string, string>  $breakdown  Y-m-d → days, from LeaveDayCounter
     * @param  array<string, string>  $balances  leave type code → days, from LeaveLedger::balances()
     */
    public function charge(LeaveType $type, array $breakdown, array $balances): Charge
    {
        $total = array_reduce($breakdown, fn (string $sum, string $days): string => bcadd($sum, $days, 3), '0.000');
        $remaining = $total;
        $draws = [];

        // A type without a balance is recorded and paid, and draws nothing.
        $sources = self::SOURCES[$type->value] ?? null;

        if ($sources === null) {
            $remaining = '0.000';
        }

        foreach ($sources ?? [] as $code) {
            $available = bccomp($balances[$code] ?? '0', '0', 3) > 0 ? bcadd($balances[$code], '0', 3) : '0.000';
            $take = bccomp($available, $remaining, 3) < 0 ? $available : $remaining;

            if (bccomp($take, '0', 3) > 0) {
                $draws[$code] = $take;
                $remaining = bcsub($remaining, $take, 3);
            }
        }

        $withPay = bcsub($total, $remaining, 3);
        $paid = $withPay;
        $dates = [];

        foreach ($breakdown as $date => $days) {
            $days = bcadd($days, '0', 3);
            $covered = bccomp($paid, $days, 3) >= 0 ? $days : $paid;
            $paid = bcsub($paid, $covered, 3);
            $dates[$date] = ['days' => $days, 'without_pay' => bcsub($days, $covered, 3)];
        }

        return new Charge($total, $withPay, $remaining, $draws, $dates);
    }
}
