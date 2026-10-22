<?php

namespace App\Models;

use Database\Factories\ScheduleAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An employee on a fixed schedule from one date, to another or for as long as
 * it lasts. An employee's assignments never overlap.
 */
#[Fillable(['employee_id', 'schedule_id', 'effective_from', 'effective_to'])]
class ScheduleAssignment extends Model
{
    /** @use HasFactory<ScheduleAssignmentFactory> */
    use HasFactory, LogsActivity;

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** "REG from Nov 1, 2026 to Nov 30, 2026", or "REG from Dec 1, 2026, with no end date". */
    public function describe(): string
    {
        $from = "{$this->schedule->code} from {$this->effective_from->format('M j, Y')}";

        return $this->effective_to === null
            ? "{$from}, with no end date"
            : "{$from} to {$this->effective_to->format('M j, Y')}";
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
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
