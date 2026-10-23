<?php

namespace App\Services\Leave;

/**
 * What an application costs: the days, how many are with pay and from which
 * balance, how many without, and each date's share of the days without pay.
 */
final readonly class Charge
{
    /**
     * @param  array<string, string>  $draws  leave type code → days drawn, in drawing order
     * @param  array<string, array{days: string, without_pay: string}>  $breakdown  Y-m-d → the date's days
     */
    public function __construct(
        public string $days,
        public string $withPay,
        public string $withoutPay,
        public array $draws,
        public array $breakdown,
    ) {}

    /** "3.000 days: 3.000 with pay (VL 3.000), 0.000 without pay", as the filing form shows it. */
    public function describe(): string
    {
        // Nothing drawn and nothing without pay only happens for a type with no balance.
        if ($this->draws === [] && bccomp($this->withoutPay, '0', 3) === 0) {
            return "{$this->days} days with pay; no balance is deducted";
        }

        $sources = $this->draws === []
            ? ''
            : ' ('.implode(', ', array_map(fn (string $code, string $days): string => "{$code} {$days}", array_keys($this->draws), $this->draws)).')';

        return "{$this->days} days: {$this->withPay} with pay{$sources}, {$this->withoutPay} without pay";
    }
}
