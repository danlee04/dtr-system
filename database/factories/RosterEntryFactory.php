<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\RosterEntry;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterEntry>
 */
class RosterEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => '2026-11-01',
            'schedule_id' => Schedule::factory()->rosterOnly(),
        ];
    }
}
