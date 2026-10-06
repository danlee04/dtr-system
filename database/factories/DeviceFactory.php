<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Main entrance',
            'ip' => fake()->localIpv4(),
            'port' => 4370,
            'comm_key' => null,
            'is_active' => true,
        ];
    }
}
