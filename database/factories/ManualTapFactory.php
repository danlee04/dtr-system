<?php

namespace Database\Factories;

use App\Enums\DtrSlot;
use App\Enums\ManualTapReason;
use App\Models\Employee;
use App\Models\ManualTap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualTap>
 */
class ManualTapFactory extends Factory
{
    /**
     * A time out on October 2 that the employee forgot to tap, copied in by HR.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => '2026-10-02',
            'slot' => DtrSlot::PmDeparture,
            'time' => '17:00',
            'reason' => ManualTapReason::ForgotToTap,
            'remarks' => null,
            'filled_by_user_id' => null,
        ];
    }
}
