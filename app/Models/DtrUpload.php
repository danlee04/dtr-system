<?php

namespace App\Models;

use App\Services\Attendance\ComputedDtr;
use App\Services\Attendance\DtrCalculator;
use Database\Factories\DtrUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One employee's DTR for a period, read from a PDF the IT Unit printed. It
 * keeps the name as printed, because that is how HR tells which employee a
 * DTR belongs to when the name matched nobody.
 */
#[Fillable(['employee_id', 'employee_name', 'period_start', 'period_end', 'file_name', 'uploaded_by_user_id'])]
class DtrUpload extends Model
{
    /** @use HasFactory<DtrUploadFactory> */
    use HasFactory, LogsActivity;

    private ?ComputedDtr $computed = null;

    /** The DTR judged against the app's schedules, worked out once per request; see DtrCalculator. */
    public function computed(): ComputedDtr
    {
        return $this->computed ??= app(DtrCalculator::class)->compute($this);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        // A DTR stays readable after its employee is removed from the list.
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** @return HasMany<DtrDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(DtrDay::class);
    }

    /** "Oct 1–31, 2026", the way the form's month line reads. */
    public function period(): string
    {
        return $this->period_start->equalTo($this->period_end)
            ? $this->period_start->format('M j, Y')
            : "{$this->period_start->format('M j')}–{$this->period_end->format('j, Y')}";
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
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }
}
