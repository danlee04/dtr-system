<?php

namespace App\Services\Attendance;

/**
 * Reads a schedule's times the way HR writes them: each time is later than
 * the one before it, and a time that is not later falls on the next day. A
 * night shift written 22:00, 02:00, 03:00, 07:00 needs no "next day" box.
 */
final class ScheduleTimes
{
    public const int DAY = 1440;

    /**
     * Minutes from the first time to each time, in order: 22:00, 02:00 gives
     * [0, 240]. A time equal to the one before it is a whole day later.
     *
     * @param  list<string>  $times  "H:i" or "H:i:s"; seconds are ignored
     * @return list<int>
     */
    public static function offsets(array $times): array
    {
        $offsets = [];
        $elapsed = 0;
        $previous = null;

        foreach ($times as $time) {
            [$hours, $minutes] = array_map(intval(...), explode(':', $time));
            $minuteOfDay = $hours * 60 + $minutes;

            if ($previous !== null) {
                $step = ($minuteOfDay - $previous + self::DAY) % self::DAY;
                $elapsed += $step === 0 ? self::DAY : $step;
            }

            $offsets[] = $elapsed;
            $previous = $minuteOfDay;
        }

        return $offsets;
    }
}
