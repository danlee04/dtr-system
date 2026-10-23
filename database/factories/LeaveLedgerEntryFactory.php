<?php

namespace Database\Factories;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveLedgerEntry>
 */
class LeaveLedgerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type' => LeaveType::Vacation,
            'posted_on' => today(),
            'days' => '1.250',
            'kind' => LedgerKind::Adjustment,
            'remarks' => 'Test',
        ];
    }
}
