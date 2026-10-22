<?php

namespace App\Models;

use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A schedule template: Regular, 4-Day Week, Driver, or a roster shift such as
 * AM, PM or Night. Employees get one through an assignment or the roster.
 */
#[Fillable([
    'code',
    'name',
    'work_days',
    'block1_in',
    'block1_out',
    'block2_in',
    'block2_out',
    'is_active',
])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory, LogsActivity;

    /** ISO weekday numbers, as `work_days` stores them. */
    public const array WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'work_days' => '[]',
        'is_active' => true,
    ];

    /** @return HasMany<ScheduleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    /** @return HasMany<RosterEntry, $this> */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }

    /**
     * The block times in order, as "H:i": two for one block, four for two.
     *
     * @return list<string>
     */
    public function times(): array
    {
        $times = array_filter(
            [$this->block1_in, $this->block1_out, $this->block2_in, $this->block2_out],
            filled(...),
        );

        return array_values(array_map(fn (string $time): string => substr($time, 0, 5), $times));
    }

    /**
     * Active schedules with work days, for a fixed assignment: one without
     * work days would make every day a rest day, so nobody would ever be late.
     * $current stays listed so an older assignment can still be edited.
     *
     * @return array<int, string> id => "REG — Regular"
     */
    public static function assignableOptions(?int $current = null): array
    {
        return static::query()
            ->orderBy('code')
            ->get()
            ->filter(fn (Schedule $schedule): bool => $schedule->getKey() === $current
                || ($schedule->is_active && $schedule->work_days !== []))
            ->mapWithKeys(fn (Schedule $schedule): array => [$schedule->getKey() => "{$schedule->code} — {$schedule->name}"])
            ->all();
    }

    /**
     * "08:00–12:00, 13:00–17:00".
     *
     * @return Attribute<string, never>
     */
    protected function hours(): Attribute
    {
        return Attribute::get(fn (): string => collect($this->times())
            ->chunk(2)
            ->map(fn ($block): string => $block->implode('–'))
            ->implode(', '));
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
            'work_days' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
