<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\CscConversion;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CscConversionTest extends TestCase
{
    #[TestWith([0, '0.000'])]
    #[TestWith([6, '0.012'], 'rounded the table\'s way, not 6 ÷ 480')]
    #[TestWith([29, '0.060'])]
    #[TestWith([59, '0.123'])]
    #[TestWith([60, '0.125'])]
    #[TestWith([72, '0.150'], 'an hour and the table\'s 12 minutes')]
    #[TestWith([480, '1.000'])]
    #[TestWith([545, '1.135'], 'past eight hours the hours keep counting')]
    public function test_converts_minutes_to_days_with_the_csc_table(int $minutes, string $days): void
    {
        $this->assertSame($days, CscConversion::days($minutes));
    }
}
