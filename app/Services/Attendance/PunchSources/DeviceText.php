<?php

namespace App\Services\Attendance\PunchSources;

/**
 * A device stores names in its own codepage, not UTF-8: "PEÑAFLOR" typed on a
 * device set to a Western codepage arrives as Windows-1252 bytes. Saved as-is,
 * MySQL's utf8mb4 would refuse the whole row.
 */
final class DeviceText
{
    /** A name read from the device, as clean UTF-8 without its field padding. */
    public static function read(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return trim($text);
    }
}
