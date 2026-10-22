<?php

namespace App\Models;

use Database\Factories\RosterEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One employee's rostered day: a schedule, or a day off when `schedule_id` is
 * null. It wins over the employee's fixed assignment for that date.
 */
#[Fillable(['employee_id', 'date', 'schedule_id'])]
class RosterEntry extends Model
{
    /** @use HasFactory<RosterEntryFactory> */
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
            // Compared with a schedule's id when the roster is saved.
            'schedule_id' => 'integer',
        ];
    }
}
