<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

/**
 * The sidebar's groups, in the order they appear: what HR does most often
 * comes first. Every screen names its group; one without a group would sit
 * above them all, beside the dashboard. The groups carry no icons, because
 * Filament allows icons on a group or on its items, not both, and the items
 * need theirs to be told apart.
 *
 * Each group has a colour for its label and its items' icons. Cyan and
 * emerald are the calm healthcare pair; violet keeps time and attendance
 * apart from both and from the amber the rest of the panel uses; slate keeps
 * administration quiet. The labels still say what each group is, so the
 * colour is never the only cue.
 */
enum NavigationGroup implements HasColor, HasLabel
{
    case Leave;
    case Personnel;
    case TimeAndAttendance;
    case Administration;

    public function getLabel(): string
    {
        return match ($this) {
            self::Leave => 'Leave',
            self::Personnel => 'Personnel',
            self::TimeAndAttendance => 'Time & attendance',
            self::Administration => 'Administration',
        };
    }

    /** @return array<int, string> a Filament palette, 50 to 950 */
    public function getColor(): array
    {
        return match ($this) {
            self::Leave => Color::Emerald,
            self::Personnel => Color::Cyan,
            self::TimeAndAttendance => Color::Violet,
            self::Administration => Color::Slate,
        };
    }

    /**
     * The name the palette is registered under: Filament prints it as
     * --nav-leave-50 … --nav-leave-950 and a .fi-color-nav-leave class.
     */
    public function colorName(): string
    {
        return 'nav-'.Str::kebab($this->name);
    }
}
