<?php

namespace Database\Factories;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveCoverage;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveApplication>
 */
class LeaveApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type' => LeaveType::Vacation,
            'date_from' => '2026-11-03',
            'date_to' => '2026-11-03',
            'coverage' => LeaveCoverage::WholeDay,
            'days' => '1.000',
            'days_with_pay' => '1.000',
            'days_without_pay' => '0.000',
            'breakdown' => ['2026-11-03' => ['days' => '1.000', 'without_pay' => '0.000']],
            'status' => LeaveApplicationStatus::Pending,
            'filed_by_user_id' => User::factory(),
        ];
    }
}
