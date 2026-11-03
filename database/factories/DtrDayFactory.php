<?php

namespace Database\Factories;

use App\Models\DtrDay;
use App\Models\DtrUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DtrDay>
 */
class DtrDayFactory extends Factory
{
    /**
     * A full day on an 8–12, 1–5 schedule: all four times, none of them late
     * or early.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dtr_upload_id' => DtrUpload::factory(),
            'date' => '2026-10-01',
            'am_arrival' => '07:51',
            'am_departure' => '12:05',
            'pm_arrival' => '12:44',
            'pm_departure' => '17:13',
            'ot_arrival' => null,
            'ot_departure' => null,
            'remarks' => null,
        ];
    }
}
