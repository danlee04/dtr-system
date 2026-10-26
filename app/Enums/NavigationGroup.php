<?php

namespace App\Enums;

use Filament\Support\Contracts\Collapsible;
use Filament\Support\Contracts\HasLabel;

/**
 * The sidebar's groups, in the order they appear: one's own leave, shown to
 * accounts linked to an employee, then what HR does most often. Every screen
 * names its group; one without a group would sit
 * above them all, beside the dashboard. A group is a heading, not a dropdown,
 * so none collapses. The groups carry no icons, because Filament allows icons
 * on a group or on its items, not both, and the items need theirs to be told
 * apart.
 */
enum NavigationGroup implements Collapsible, HasLabel
{
    case MyLeave;
    case Leave;
    case Personnel;
    case TimeAndAttendance;
    case Administration;

    public function getLabel(): string
    {
        return match ($this) {
            self::MyLeave => 'My leave',
            self::Leave => 'Leave',
            self::Personnel => 'Personnel',
            self::TimeAndAttendance => 'Time & attendance',
            self::Administration => 'Administration',
        };
    }

    public function isCollapsible(): bool
    {
        return false;
    }

    public function isCollapsed(): bool
    {
        return false;
    }
}
