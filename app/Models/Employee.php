<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'employee_number',
    'last_name',
    'first_name',
    'middle_name',
    'suffix',
    'position',
    'photo_path',
    'office_id',
    'employment_status',
    'biometric_id',
    'date_hired',
    'date_separated',
    'is_active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /** The private disk and folder their photos are kept in, never the public one. */
    public const string PHOTO_DISK = 'local';

    public const string PHOTO_DIRECTORY = 'employee-photos';

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        // A replaced or removed photo leaves no file behind.
        static::updated(function (Employee $employee): void {
            $old = $employee->getOriginal('photo_path');

            if ($employee->wasChanged('photo_path') && filled($old)) {
                Storage::disk(self::PHOTO_DISK)->delete($old);
            }
        });
    }

    /** A link to their photo that works for half an hour, or null without one. */
    public function photoUrl(): ?string
    {
        return filled($this->photo_path)
            ? Storage::disk(self::PHOTO_DISK)->temporaryUrl($this->photo_path, now()->addMinutes(30))
            : null;
    }

    /**
     * Their photo, or their initials as a small SVG built here: no name sent
     * to an avatar service.
     */
    public function avatarUrl(): string
    {
        if (($photo = $this->photoUrl()) !== null) {
            return $photo;
        }

        $initials = mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#fef3c7"/>'
            .'<text x="32" y="32" dy="0.35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" font-weight="600" fill="#92400e">'
            .htmlspecialchars($initials, ENT_XML1)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** @return BelongsTo<Office, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** @return HasMany<ScheduleAssignment, $this> */
    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    /** The fixed schedule assigned for a date, or null; a roster's day is the roster's. */
    public function scheduleOn(CarbonImmutable $date): ?Schedule
    {
        return $this->scheduleAssignments()
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->latest('effective_from')
            ->with('schedule')
            ->first()?->schedule;
    }

    /** @return HasMany<RosterEntry, $this> */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }

    /** @return HasMany<LeaveApplication, $this> */
    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    /** @return HasMany<LeaveLedgerEntry, $this> */
    public function leaveLedgerEntries(): HasMany
    {
        return $this->hasMany(LeaveLedgerEntry::class);
    }

    /**
     * "Dela Cruz, Juan S. Jr." — surname first, the way HR sorts and reads a
     * list.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = "{$this->last_name}, {$this->first_name}";

            if (filled($this->middle_name)) {
                $name .= ' '.mb_substr($this->middle_name, 0, 1).'.';
            }

            if (filled($this->suffix)) {
                $name .= ' '.$this->suffix;
            }

            return $name;
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
            'employment_status' => EmploymentStatus::class,
            'date_hired' => 'date',
            'date_separated' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
