<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a leave ledger row records. The system posts credits, grants, lapses
 * and forced leave; HR enters opening balances and adjustments.
 */
enum LedgerKind: string implements HasLabel
{
    case Opening = 'opening';
    case MonthlyCredit = 'monthly_credit';
    case YearlyGrant = 'yearly_grant';
    case Lapse = 'lapse';
    case ForcedLeave = 'forced_leave';
    case Adjustment = 'adjustment';
    case Application = 'application';
    case Cancellation = 'cancellation';
    case CreditCorrection = 'credit_correction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Application => 'Leave application',
            self::Cancellation => 'Cancelled application',
            self::CreditCorrection => 'Credit correction',
            self::Opening => 'Opening balance',
            self::MonthlyCredit => 'Monthly credit',
            self::YearlyGrant => 'Yearly grant',
            self::Lapse => 'Lapsed',
            self::ForcedLeave => 'Forced leave not taken',
            self::Adjustment => 'Adjustment',
        };
    }
}
