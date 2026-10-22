<?php

namespace App\Models;

use App\Enums\HolidayType;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A non-working day or a work suspension. It applies to fixed schedules only;
 * rostered staff work the roster.
 */
#[Fillable(['date', 'name', 'type', 'suspension_starts_at'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory, LogsActivity;

    /**
     * A time only means something on a suspension. The form hides the field
     * for other types, and a hidden field is not saved, so an old time would
     * otherwise stay behind.
     */
    protected static function booted(): void
    {
        static::saving(function (Holiday $holiday): void {
            if ($holiday->type !== HolidayType::Suspension) {
                $holiday->suspension_starts_at = null;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => HolidayType::class,
        ];
    }
}
