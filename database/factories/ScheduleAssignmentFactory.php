<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleAssignment>
 */
class ScheduleAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'schedule_id' => Schedule::factory(),
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ];
    }
}
