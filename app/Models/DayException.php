<?php

namespace App\Models;

use App\Enums\ExceptionType;
use App\Enums\LeaveCoverage;
use Database\Factories\DayExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An official business or a travel order, entered by HR from the signed
 * slip. Its dates are not late, undertime or absent on the DTR; a morning or
 * an afternoon covers that half of one date. Coverage reads like leave's, so
 * it reuses LeaveCoverage.
 */
#[Fillable(['employee_id', 'type', 'date_from', 'date_to', 'coverage', 'reference_no', 'remarks', 'created_by_user_id'])]
class DayException extends Model
{
    /** @use HasFactory<DayExceptionFactory> */
    use HasFactory, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'coverage' => 'whole_day',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** "Oct 26–30, 2026", or "Oct 13, 2026 (afternoon)" for half a day. */
    public function dates(): string
    {
        return match (true) {
            $this->date_from->equalTo($this->date_to) => $this->date_from->format('M j, Y').match ($this->coverage) {
                LeaveCoverage::FirstHalf => ' (morning)',
                LeaveCoverage::SecondHalf => ' (afternoon)',
                default => '',
            },
            $this->date_from->format('Y-m') === $this->date_to->format('Y-m') => "{$this->date_from->format('M j')}–{$this->date_to->format('j, Y')}",
            default => "{$this->date_from->format('M j, Y')} – {$this->date_to->format('M j, Y')}",
        };
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
            'type' => ExceptionType::class,
            'date_from' => 'date',
            'date_to' => 'date',
            'coverage' => LeaveCoverage::class,
        ];
    }
}
