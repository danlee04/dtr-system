<?php

namespace Database\Factories;

use App\Enums\ExceptionType;
use App\Enums\LeaveCoverage;
use App\Models\DayException;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DayException>
 */
class DayExceptionFactory extends Factory
{
    /**
     * A whole-day official business on a Monday in October.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => ExceptionType::OfficialBusiness,
            'date_from' => '2026-10-26',
            'date_to' => '2026-10-26',
            'coverage' => LeaveCoverage::WholeDay,
            'reference_no' => null,
            'remarks' => null,
            'created_by_user_id' => null,
        ];
    }
}
