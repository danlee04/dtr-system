<?php

namespace Database\Factories;

use App\Models\DtrUpload;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DtrUpload>
 */
class DtrUploadFactory extends Factory
{
    /**
     * October, for an employee whose name the DTR prints the way the list
     * spells it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'employee_name' => fn (array $attributes): string => $attributes['employee_id'] === null
                ? strtoupper(fake()->lastName().', '.fake()->firstName())
                : Employee::find($attributes['employee_id'])->full_name,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'file_name' => fn (array $attributes): string => "{$attributes['employee_name']}.pdf",
            'uploaded_by_user_id' => null,
        ];
    }

    /** A name that matched no employee, waiting for HR to choose one. */
    public function unmatched(): static
    {
        return $this->state(['employee_id' => null]);
    }
}
