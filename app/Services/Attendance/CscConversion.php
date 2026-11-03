<?php

namespace App\Services\Attendance;

/**
 * Minutes late and under as days of leave, by the CSC table for an
 * eight-hour day. The month's total is converted, not each day.
 */
final class CscConversion
{
    /**
     * The table's minutes as fractions of a day. Kept as the table prints
     * them rather than minutes ÷ 480, which rounds some differently: 6
     * minutes is 0.012, not 0.013.
     *
     * shortcut: copied from the spec's table; check it against HR's printed copy before the VL deduction uses it.
     */
    private const MINUTES = [
        '0.000', '0.002', '0.004', '0.006', '0.008', '0.010', '0.012', '0.015', '0.017', '0.019',
        '0.021', '0.023', '0.025', '0.027', '0.029', '0.031', '0.033', '0.035', '0.037', '0.040',
        '0.042', '0.044', '0.046', '0.048', '0.050', '0.052', '0.054', '0.056', '0.058', '0.060',
        '0.062', '0.065', '0.067', '0.069', '0.071', '0.073', '0.075', '0.077', '0.079', '0.081',
        '0.083', '0.085', '0.087', '0.090', '0.092', '0.094', '0.096', '0.098', '0.100', '0.102',
        '0.104', '0.106', '0.108', '0.110', '0.112', '0.115', '0.117', '0.119', '0.121', '0.123',
    ];

    /** "0.150" for 72 minutes: each whole hour is 0.125, and the rest comes from the table. */
    public static function days(int $minutes): string
    {
        return bcadd(bcmul((string) intdiv($minutes, 60), '0.125', 3), self::MINUTES[$minutes % 60], 3);
    }
}
