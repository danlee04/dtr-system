<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('S###'),
            'name' => fake()->words(2, true),
            'work_days' => [1, 2, 3, 4, 5],
            'block1_in' => '08:00:00',
            'block1_out' => '12:00:00',
            'block2_in' => '13:00:00',
            'block2_out' => '17:00:00',
            'is_active' => true,
        ];
    }

    /** A shift used only on the duty roster, which names its own dates. */
    public function rosterOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'work_days' => [],
        ]);
    }
}
