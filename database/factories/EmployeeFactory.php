<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => fake()->unique()->numerify('####-####'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->lastName(),
            'suffix' => null,
            'office_id' => Office::factory(),
            'employment_status' => EmploymentStatus::Permanent,
            'biometric_id' => null,
            'date_hired' => null,
            'date_separated' => null,
            'is_active' => true,
        ];
    }

    public function withBiometricId(?string $biometricId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'biometric_id' => $biometricId ?? (string) fake()->unique()->numberBetween(1, 99999),
        ]);
    }
}
